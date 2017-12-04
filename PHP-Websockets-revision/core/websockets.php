<?php

//require_once('./daemonize.php');
require_once('./core/users.php');
//require_once('./eventloop/socket.php');

class core_websockets {
  const CLOSE_NORMAL      = 1000;
  const CLOSE_GOING_AWAY  = 1001;
  const CLOSE_PROTOCOL    = 1002;
  const CLOSE_BAD_DATA    = 1003;
  const CLOSE_NO_STATUS   = 1005; //internal code
  const CLOSE_ABNORMAL    = 1006; //internal code
  const CLOSE_BAD_PAYLOAD = 1007;
  const CLOSE_POLICY      = 1008;
  const CLOSE_TOO_BIG     = 1009;
  const CLOSE_MAND_EXT    = 1010;
  const CLOSE_SRV_ERR     = 1011;
  const CLOSE_TLS         = 1015;

  // Configuration Start 
  protected $debug_mode				              = true; // debug tool I left in code. verbose mode
  protected $max_request_handshake_size 	  = 1024; // chrome : ~503B firefox : ~530B IE 11 : ~297B 
  // There is no way to use http status code to send some application error to client we MUST open the connection first
  protected $max_client                 	  = 1000;  // 1024 is the max with select() keep space for rejecting socket I suggest keeping 24
  protected $error_maxclient	 		            = "WS SERVER reach it maximum limit. Please try again later"; // Set the error message sent to client. 
  protected $headerProtocolRequired         = false;
  protected $willSupportExtensions          = false;  // Turn it to true if you support any extensions

  // TODO : these 2 variables will be used to protect OOM and dynamically set max_client based on mem allowed per user
  protected $max_writeBuffer			  = 49152; //48K out 
  protected $max_readBuffer			    = 49152; //48K in 
  // Configuration End
  
  protected $userClass = 'WebSocketUser'; // redefine this if you want a custom user class.  The custom user class should inherit from WebSocketUser.
  protected $maxBufferSize;        
  protected $master;
  protected $readWatchers                         = array();
  protected $writeWatchers                        = null;
  protected $users                                = array();
  protected $interactive                          = true;
  protected $nbclient                             = 0;
  protected $apps;

  protected $mem;

  private $min   = 0;
  private $max   = 0;
  private $total = 0;
  private $nb    = 0;
  private $last  = 0;
  private $limit = 1000;
  private $firsttime =0;
  private $totaltime=0;
  
 function __construct($addr, $port, $bufferLength = 16384 ) {
    $this->maxBufferSize = $bufferLength;
    $this->master = $this->ws_server($addr,$port);
    $this->sockets['m'] = $this->master;
    $this->stdout("Server started\nListening on: $addr:$port\nMaster socket: ".$this->master);
  }

  protected function connecting($user) {
    // Override to handle a connecting user, after the instance of the User is created, but before
    // the handshake has completed.
  }

  //here an example how to use a single ws app to handle multiple virtualhost / 
  protected function broadcast($msg,&$sender) {
    $vhost=$sender->headers['host'].$sender->headers['get'];
    $message = $this->frame($msg);
    foreach ($this->users as $user) {
      if ($user->readystate === $user::OPEN ) { // prevent sending data to user who have not complete the handshake session or in closing process.
        if ($vhost ==($user->headers['host'].$user->headers['get'])) {
          $this->stdout("same channel ".$channel,true);
          $this->ws_write($user, $message);
        }
      }
    }
  }

  //prototype for new outgoing message system with command like close within
  //this will allow to keep thing related to websocket within this class
  //name to be change
  //TODO : add broadcast as opcode to allow broadcast to be called here.
  protected function send_proto($obj) {
    //var_dump($obj);
    switch($obj->opcode) {
      case $obj::SEND : 
        $this->send($obj->users,$obj->message);
        break;
      case $obj::BINARY : 
        $this->send($obj->users,$obj->message,'binary');
        break;
      case $obj::CLOSE : 
        $this->disconnect($obj->users,self::CLOSE_NORMAL,$obj->message);
        break;
    }
  }
   
  protected function send(&$user,$message,$type='text') {
    //$this->stdout("> $message");
    $message = $this->frame($message,$type);
    $this->ws_write($user, $message);
  }

  /**
   * Read callback on socket
   */

  protected function cb_read(&$user) {
    $socket=$user->socket;
    $numBytes = $this->ws_read_t($socket,$buffer,$this->maxBufferSize); 
    if ($numBytes === false) {
      $this->stdout('Socket error: ' . socket_strerror(socket_last_error($socket)));
    }
    elseif ($numBytes == 0) {
      $this->disconnect($user);
      $this->stdout("Client disconnected. TCP connection lost: " . $socket);
    } 
    else {
      if ($user->readystate === $user::CONNECTING) {
        if ($user->handlingPartialPacket) {
          $buffer=$user->readBuffer . $buffer;
        }
        //OOM protection:  prevent buffer overflow from malicious client.
        if ( strlen($buffer)  > $this->max_request_handshake_size ) {
	        $handshakeResponse = "HTTP/1.1 413 Request Entity Too Large"; 
          $this->ws_write($user,$handshakeResponse);
          $this->disconnect($user);
        }
      	else {
	        // If the client has finished sending the header, otherwise wait before sending our upgrade response.
	        if (strpos($buffer, "\r\n\r\n") !== FALSE ) {
            $this->doHandshake($user,$buffer);
            // after handshake successfull check for maximum client reach and send msg + close the connection
            // todo : admin dashboard will not be counted allowing you to connect to it even if the server is full.
            //        
            if ($this->nbclient>$this->max_client) {
              $message = $this->frame($this->error_maxclient);
              //var_dump($message);
              $this->ws_write($user,$message);
              $this->disconnect($user,1000);
            }
            else {
              //clear buffer & state
              $user->handlingPartialPacket=FALSE;
              $user->readBuffer='';
            }
          }
          else {
            //store partial packet to be analysed in next call
            $user->readBuffer=$buffer;
            $user->handlingPartialPacket=TRUE;
          }
        }
      } 
      else {
	      $this->split_packet($numBytes,$buffer, $user);
      }
    }
  }


  /* wrapping socket_recv for future implementation of libevent library 
  protected function ws_read($user,) {
    socket_recv($socket,$buffer,$this->maxBufferSize,0);
    return $buffer;
  }*/

  // buffering outgoing data if requiered and wrapping socket_write() for future implementation of libevent library
  protected function ws_write(&$user,$message='') {
    //wrapper function to process internal write handler
    $user->writeBuffer.=$message;
    $sizeBuffer = strlen($user->writeBuffer);
    $sent = $this->ws_write_t($user->socket, $user->writeBuffer);
    if ($sent<$sizeBuffer) {
      //adjust the buffer
      $user->writeBuffer=substr($user->writeBuffer,$sent);
      //set watcher to write mode
      if (!$user->writeNeeded) {
        $this->stdout(">>> Start write watchers",true);
	      $user->writeNeeded=true;
        $this->addWriteWatchers($user);
      }
      $this->stdout(">>size msg $sizeBuffer sent $sent",true);
    }
    else {
      //clear buffer and remove write flag and watcher
      $user->writeBuffer='';
      if ($user->writeNeeded){
        $this->stdout("<<< Stop write watchers",true);
        $user->writeNeeded=false;
        $this->removeWriteWatchers($user);
      }
    }
  }

  protected function showmem(){
    $t=memory_get_usage()-$this->mem;
    $this->stdout("mem total : ".memory_get_usage()." diff : ".$t." nbclient = $this->nbclient",true);
  }

  protected function connect($socket) {
    $this->nbclient++;
    $user = new $this->userClass(uniqid('u'), $socket);
    $this->users[$user->id] = &$user;
    $this->connecting($user);
    return $user;
  }

  protected function disconnect(&$user, $errorcode = false, $reason='') {  
    if ($user !== null) {
      echo ">>  readystate ; $user->readystate\n";
      //if (!$errorcode) {
      if ($user->readystate !== $user::OPEN) {
        if(method_exists($this->apps[$user->headers['get']],"onclose"))
          $this->apps[$user->headers['get']]->onclose($user);
          $this->ws_close_t($user->socket);
      }
      else {
        $message = $this->frame($errorcode.':'.$reason, 'close');
        //$message = $this->frame('', 'close');
        $this->ws_write($user, $message);
        $user->readystate=$user::CLOSING;
        $user->willclose=true;
        $this->ws_close_t($user->socket);
        return;
      }

      $this->nbclient--;
      $this->stdout("Client disconnected. ".$user->socket);
      //remove data used by internal method
      //$this->readWatchers[$user->id]->stop();
      //free memory before sending variable to be garbage collected
      $this->readWatchers[$user->id]=null;
      unset($this->readWatchers[$user->id]);
              
      //remove any data from writeWatchers too
      if ($user->writeNeeded) {
        $this->removeWriteWatchers($user);
      }
      $this->users[$user->id]=null;
      unset($this->users[$user->id]);
      $user=null;
      unset($user);
    }
  }


  //todo finish this
  private function check_headers($headers) {
    //var_dump($headers);
    //check every required token isset first and then check it's content
    if (!isset($headers['get'])) {
      return "HTTP/1.1 405 Method Not Allowed";     
    }
     
    if (!isset($headers['http'],
               $headers['host'],
               $headers['upgrade'],
               $headers['connection'],
               $headers['sec-websocket-key'],
               $headers['sec-websocket-version']/*,
               $headers['origin']*/) ||
        (strtolower($headers['upgrade']) !== 'websocket')  || 
        (strpos(strtolower($headers['connection']),'upgrade') === FALSE) || 
        (!$this->checkHostOrigin($headers['host']))) {
      return "HTTP/1.1 400 Bad Request";
    }
   
    if (!isset($this->apps[$headers['get']])) {
      return "HTTP/1.1 404 Not Found";
    }
    if ($headers['http'] < 1.1 ) {
      return "HTTP/1.1 505 HTTP Version not supported";
    }
    /*if (!$this->checkHostOrigin($headers['origin'])) {
      return "HTTP/1.1 403 Forbidden";
    }*/
    if ($headers['sec-websocket-version'] != 13) {
      return "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
    }  
    return null;
  }

  private function doHandshake($user, $buffer) {
    $magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
    $headers = array();
    $lines = explode("\r\n",$buffer);
    //prevent warning php7
    $subProtocol='';
    $extensions='';
    $headers['sec-websocket-extensions']='';
    $headers['sec-websocket-protocol']='';

    //protocol and extensions can be sent on multiple line.
    //$headers['sec-websocket-protocol']='';
    //$headers['sec-websocket-extensions']='';
    foreach ($lines as $line) {
      if (strpos($line,":") !== false) {
        $token = explode(":",$line,2);
        $token[0]=strtolower(trim($token[0]));
        switch ($token[0]) {
          case 'sec-websocket-protocol':
            $headers[$token[0]] .= trim($token[1]).', ';
            break;
          case 'sec-websocket-extensions':
            $headers[$token[0]] .= trim($token[1]).'; ';
            break;
          default : $headers[$token[0]] = trim($token[1]);
        }
      }
      elseif (stripos($line,"get ") !== false) {
        $token = explode(" ",$line);
        $headers['get'] = trim($token[1]);
        $subtoken = explode("/",$token[2]);
        $headers['http'] = (float)trim($subtoken[1]);
      }
    }
    // to remove
    $headers['get']="/echobot";
    //
    $handshakeResponse = $this->check_headers($headers);
    
    /*TODO : implement protocol - untill then no need of this code
    // Protocol work on message level. So you can enforce it
    $protocol= $this->checkProtocol(explode(', ',$headers['sec-websocket-protocol']));
    if (($this->headerProtocolRequired && !isset($headers['sec-websocket-protocol'])) || ($this->headerProtocolRequired && !$protocol)) {
      $handshakeResponse = "HTTP/1.1 400 Bad Request";
    }
    else if ($protocol){
      $user->headers["protocol"] = $protocol;
      $subProtocol="Sec-WebSocket-Protocol: ".$protocol."\r\n";
    }
    */

    // Done verifying the _required_ headers and optionally required headers.

    if (isset($handshakeResponse)) {
      $this->ws_write($user,$handshakeResponse);
      $this->disconnect($user);
      return;
    }

	  // Keeping only relevant token that can be of use after handshake
    // protocol get host extensions
    $user->headers["get"] =  $headers["get"];
    $user->headers["host"] = $headers["host"];
    
    /*TODO: implement extension - untill then no need of this code
    // Not all browser support same extensions.
    // extensions work on frame level
    $extensionslist = $this->checkExtensions(explode('; ',$headers['sec-websocket-extensions']));
    if ($this->willSupportExtensions && !$extensions) {
      $user->headers["extensions"] = $extensionslist;
      $extensions = "Sec-WebSocket-Extensions: ".$extensionslist."\r\n";
    }
    */

    $user->readystate = $user::OPEN;

    $webSocketKeyHash = sha1($headers['sec-websocket-key'] . $magicGUID);

    $rawToken = "";
    for ($i = 0; $i < 20; $i++) {
      $rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
    }
    $handshakeToken = base64_encode($rawToken) . "\r\n";

    $handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";
    $this->ws_write($user,$handshakeResponse);
    //var_dump($this->apps);

    $this->apps[$user->headers['get']]->onopen($user);
  }

  private function checkHostOrigin($host) {
    switch($host) {
      case 'localhost:8080':
      case 'localhost:8082':
      case 'localhost:8083':
      case 'localhost:8084':
      case 'example.com:8080':   // host check
      case 'http://example.com' : // origin check
        return true;
      default : 
        return false;
    }
  }

  private function checkExtensions($extensions) {
    // TODO : add extensions support. you can support multiple extensions but right now the list supported by
    // browser is very thin.
    return false; // Override and return name of extensions supported on server.
  }

  private function checkProtocol($protocols) {
    // Note : If the client specified subprotocol you MUST choose ONLY ONE  of the list otherwise 
    // the client will close the connection.  It's FIFO order sent by client.
    return false;
  }

  public function stdout($message,$debug=false) {
    if ($this->interactive) {
      if (($this->debug_mode && $debug) || !$debug) {
        echo "$message\n";
      }
    }
  }

  private function frame($message, $messageType='text', $messageContinues=false) {
    switch ($messageType) {
      case 'continuous': $bytes[1] = 0;
        break;
      case 'text': $bytes[1] = ($messageContinues) ? 0 : 1;
        break;
      case 'binary': $bytes[1] = ($messageContinues) ? 0 : 2;
        break;
      case 'close': $bytes[1] = 8;
        if ($message!=='') {
          $token = explode(':',$message,2);
          // statuscode:reason
          //convert status code into 2 bytes and add reason after that
          $message  = chr(( $token[0] >> 8 ) & 255);
          $message .= chr(( $token[0]      ) & 255);
          $message .= $token[1];
        }
        break;
      case 'ping': $bytes[1] = 9;
        break;
      case 'pong': $bytes[1] = 10;
        break;
    }
    if (!$messageContinues) {
      $bytes[1] += 128;
    } 

    $length = strlen($message);
    if ($length < 126) {
      $bytes[2] = $length;
    } 
    elseif ($length < 65536) {
      $bytes[2] = 126;
      $bytes[3] = ( $length >> 8 ) & 255;
      $bytes[4] = ( $length      ) & 255;
    } 
    else {
      $bytes[2] = 127;
      $bytes[3] = ( $length >> 56 ) & 255;
      $bytes[4] = ( $length >> 48 ) & 255;
      $bytes[5] = ( $length >> 40 ) & 255;
      $bytes[6] = ( $length >> 32 ) & 255;
      $bytes[7] = ( $length >> 24 ) & 255;
      $bytes[8] = ( $length >> 16 ) & 255;
      $bytes[9] = ( $length >>  8 ) & 255;
      $bytes[10]= ( $length       ) & 255;
    }
    $headers = "";
    foreach ($bytes as $chr) {
      $headers .= chr($chr);
    }
    return $headers . $message;
  }


  //check packet if he have more than one frame and process each frame individually
  private function split_packet($length,$packet,$user) {
    //add PartialPacket and calculate the new $length
    if ($user->handlingPartialPacket) {
      $packet = $user->readBuffer . $packet;
      $user->handlingPartialPacket = false;
      $length=strlen($packet);
      //free buffer memory
      $user->readBuffer=null;
    }
    $fullpacket=$packet;
    $frame_pos=0;
    $frame_id=1;
    while($frame_pos<$length) {
      $headers = $this->extractHeaders($packet);
      $this->stdout("packet size : ".$length." frame #".$frame_id." position : ".$frame_pos."\n  msglen : ".$headers['length'].
        " offset : ".$headers['offset']." = framesize of ".($headers['offset']+$headers['length']),true);

      //check if we deal with incomplete frame within packet.
      if ($headers['offset']+$headers['length'] > $length-$frame_pos) {
        $user->handlingPartialPacket = true;
        $user->readBuffer = $packet;
        break;
      }
      else if (($message = $this->deframe($packet, $user,$headers)) !== FALSE) {
        if ($user->hasSentClose) {
          if ((preg_match('//u', $message))) {
            $this->disconnect($user,SELF::CLOSE_NORMAL,$message);
          }
          else 
            $this->disconnect($user,SELF::CLOSE_PROTOCOL);
        } else {
          //UTF_8 or binary
          if ($headers['opcode']===2) {
            $this->send_proto($this->apps[$user->headers['get']]->onbinary($user, $message));
          } else if (preg_match('//u', $message)) {
            $this->send_proto($this->apps[$user->headers['get']]->onmessage($user, $message));           
          } else {
            $this->disconnect($user,SELF::CLOSE_BAD_PAYLOAD);
            $this->stdout("not UTF-8\n",true);
          }
        }
      }
      if (!$user->willclose) {
        //get the new position also modify packet data
        $frame_pos+=$headers['offset']+$headers['length'];
        $packet=substr($fullpacket,$frame_pos);
        $frame_id++;
      }
      else 
        $frame_pos=$length;
    }
    $this->stdout("########    PACKET END         #########",true);
  }

  private function deframe($packet, &$user,$headers) {
    // echo $this->strtohex($message);
    // get payload as now we are sure are completely sent
    $payload=substr($packet,$headers['offset'],$headers['length']);
    if ($this->checkRSVBits($headers,$user)) {
      return false;
    }
    $willClose = false;
    $firstopcode = 0;
    switch($headers['opcode']) {
      case 0: /*Continuous*/  
        if (!$user->opcode || $user->fin) {
          $this->disconnect($user,SELF::CLOSE_PROTOCOL);
          $user->willclose=true;
          return false;
        }
        else {
          $firstopcode =$user->opcode;
          if ($headers['fin'])
            $user->opcode=false;
          $user->fin=$headers['fin'];
           
        }
        break;
      case 1: /*Text*/
      case 2: /*Binary*/
        //bypass utf-8 check if opcode or firstframeopcode == 2
        if (!$headers['fin']) {
          $user->opcode=$headers['opcode'];
          $user->fin=false;
        }
        else {
          if (!$user->fin && $user->opcode) {
            $this->disconnect($user,SELF::CLOSE_PROTOCOL);
            //$user->willclose=true;
            return false;
          }
          $user->opcode=false;
          $user->fin=true;
        }
        break;
      case 8: /*Close*/
        // todo: close the connection
        if ($headers['length']==0) {
          $this->disconnect($user,SELF::CLOSE_NORMAL);
          return false;
        }
        if ($headers['length']<126 && $headers['length']>1 ) {
          $reason='';
          $unmasked = $this->applyMask($headers,$payload);
          if ($headers['length']>3)
            $reason=substr($unmasked,3);
          $code = (ord($unmasked[0]) << 8  ) | ( ord($unmasked[1]));

          if (($code==SELF::CLOSE_NORMAL) ||
              ($code==SELF::CLOSE_GOING_AWAY) ||
              ($code==SELF::CLOSE_PROTOCOL) || 
              ($code==SELF::CLOSE_BAD_DATA) || 
              ($code==SELF::CLOSE_BAD_PAYLOAD) ||
              ($code==SELF::CLOSE_POLICY) ||
              ($code==SELF::CLOSE_TOO_BIG) ||
              ($code==SELF::CLOSE_MAND_EXT) ||
              ($code==SELF::CLOSE_SRV_ERR) || ($code>=3000 && $code <5000)){
            $user->hasSentClose = true;
            return $reason;
          } else {
            $this->disconnect($user,SELF::CLOSE_PROTOCOL);
            return false;
          }
            
        } else {
          $this->disconnect($user,SELF::CLOSE_PROTOCOL);
          return false;
        }
        
      case 9: /*Ping*/
        if ($headers['length']<126 && $headers['fin']==1) {
          $reply = $this->frame($this->applyMask($headers,$payload),'pong');
          $this->ws_write($user,$reply);
        }
        else 
          $this->disconnect($user,SELF::CLOSE_PROTOCOL);
        return false;
      case 10: /*Pong*/
        if ( $headers['fin']==0 )
          $this->disconnect($user,SELF::CLOSE_PROTOCOL);
	      //A Pong frame MAY be sent unsolicited. This serves as a unidirectional
        //heartbeat. A response to an unsolicited Pong frame is not expected.
	      //IE 11 default behavior send PONG ~30sec between them. Just ignore them.
        return false;  
      default: 
        $this->disconnect($user,SELF::CLOSE_PROTOCOL);
        return false;
    }

    //add unmask payload to partialMessage who handle continuous message allready unmasked
    $payload = $user->partialMessage . $this->applyMask($headers,$payload);

    if ($headers['fin']) {
      $user->partialMessage = '';
      return $payload;
    }
    $user->partialMessage = $payload;
    return false;
    
  }

  private function trimendUTF8($data,$size) {
    //check last 4 bytes for UTF8 codepoint
    $end = 4;
    if ( $size < $end )
      $end=$size;
    
    for ( $pos = 1 ; $pos<=$end ; $pos++ ) {
      $current = ord($data[$size-$pos]);
      if (($current & 0xF8) == 0xF0) { return ($pos == 4) ? 0 : $pos; } 
      if (($current & 0xF0) == 0xE0) { return ($pos == 3) ? 0 : $pos; } 
      if (($current & 0xE0) == 0xC0) { return ($pos == 2) ? 0 : $pos; }
      if (($current & 0x80) == 0x00) { if ($pos == 1 ) return 0; }
    }
    //Invalid tail string UTF8 ( skip any more utf8 check )
    return false;
  }

  private function extractHeaders($message) {
    //fix bug where fin rsv1 rsv2 and rsv3 was string instead of int type
    $header = array(
      'fin'       => ord($message[0])>>7 & 1,
      'rsv1'      => ord($message[0])>>6 & 1,
      'rsv2'      => ord($message[0])>>5 & 1,
      'rsv3'      => ord($message[0])>>4 & 1,
      'opcode'    => ord($message[0] & chr(15)),
      'hasmask'   => ord($message[1])>>7 & 1,
      'length'    => 0,
      'indexMask' => 2,
      'offset'    => 0,
      'mask'      => "");

    $header['length'] = ord($message[1] & chr(127)) ;
	
    if ($header['length'] == 126) {
      $header['indexMask'] = 4;
      $header['length'] =  (ord($message[2])<<8) | (ord($message[3])) ;
    } 
    elseif ($header['length'] == 127) {
      $header['indexMask'] = 10;
      $header['length'] = (ord($message[2]) << 56 ) | ( ord($message[3]) << 48 ) | ( ord($message[4]) << 40 ) |                                                       (ord($message[5]) << 32 ) | ( ord($message[6]) << 24 ) | ( ord($message[7]) << 16 ) |
		                      (ord($message[8]) << 8  ) | ( ord($message[9])) ;
    } 
    $header['offset']=$header['indexMask'];
    if ($header['hasmask']) {
      $header['mask'] = $message[$header['indexMask']] . $message[$header['indexMask']+1] . 
		        $message[$header['indexMask']+2] . $message[$header['indexMask']+3];
      $header['offset']+=4;
    }
    //var_dump($header);
    return $header;
  }

  private function applyMask($headers,$payload) {
    $effectiveMask = "";
    if ($headers['hasmask']) {
      $mask = $headers['mask'];
    } 
    else {
      return $payload;
    }

    $effectiveMask = str_repeat($mask , ($headers['length']/4)+1 );
    $over=$headers['length']-strlen($effectiveMask);
    $effectiveMask=substr($effectiveMask,0,$over);
    
    return $effectiveMask ^ $payload;
  }

  protected function checkRSVBits($headers,$user) { // override this method if you are using an extension where the RSV bits are used.
    if ($headers['rsv1'] + $headers['rsv2']+ $headers['rsv3'] > 0) {
      $this->disconnect($user,SELF::CLOSE_PROTOCOL);
      return true;
    }
    return false;
  }

  protected function getrps($start = false,$source = '') {
    if (!$start) {
      $time=microtime(true);
      //if ($this->nbclient==1)
      if ($this->nb==6)
        $this->firsttime=$time;
      
      return $time;
    }
    else {
      $end = microtime(true);
      $time = $end-$start;
      $this->totaltime+=$time;
      $rps = floor(1/$time);

      $this->add($rps,$time);
      
      $this->stdout("$source time $time -- $rps prps",true); // prps = potential requests per second
      $this->showmem();
      //if ($this->nbclient==512) {
        $ti=$end-$this->firsttime;
        $this->stdout("####\n# $ti - $this->totaltime - $this->nb \n####");
      //}

      $this->stdout($this->show());
    }

  }

   function add($new) {
    if ($this->nb==0) {
      $this->total+=$new;
      $this->min=$new;
      $this->max=$new;
      
    }
    else if ($this->nb==$this->limit) {
      if ($new<$this->min) $this->min= $new;
      if ($new>$this->max) $this->max= $new;
      $this->total+=$new-$this->last;
    }
    else {
      if ($new<$this->min) $this->min= $new;
      if ($new>$this->max) $this->max= $new;
      $this->total+=$new;
    }
    $this->nb++;
    $this->last=$new;
    
  }

  function show() {
    $avg= floor($this->total/$this->nb);
    $echo = "min = $this->min - max = $this->max - avg = $avg on $this->nb values";
    return $echo;
  }
 
}

