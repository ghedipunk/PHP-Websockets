<?php
/**
 * Contains the core Websockets server class
 */

namespace Gwps/Core;

class Router {

    // Configuration Start 
    private $debug_mode                 = false; // debug tool I left in code. verbose mode
    private $max_request_handshake_size     = 1024; // chrome : ~503B firefox : ~530B IE 11 : ~297B 
    // There is no way to use http status code to send some application error to client we MUST open the connection first
    private $max_client                     = 100;  // 1024 is the max with select() keep space for rejecting socket I suggest keeping 24
    private $error_maxclien             = "WS SERVER reach it maximum limit. Please try again later"; // Set the error message sent to client. 
    private $headerOriginRequired                 = false;
    private $headerProtocolRequired               = false;
    private $willSupportExtensions                = false;  // Turn it to true if you support any extensions

    // TODO : these 2 variables will be used to protect OOM and dynamically set max_client based on mem allowed per user
    private $max_writeBuffer            = 49152; //48K out 
    private $max_readBuffer             = 49152; //48K in 
    // Configuration End
    
    private $userClass = 'WebSocketUser'; // redefine this if you want a custom user class.  The custom user class should inherit from WebSocketUser.
    private $maxBufferSize;        
    private $master;
    private $readWatchers                         = array();
    private $writeWatchers                        = null;
    private $users                                = array();
    private $interactive                          = true;
    private $nbclient                             = 0;


    private $mem;

    private $eventHandlers = array();
     
    function __construct($addr, $port, $bufferLength = 16384 ) {
        $this->maxBufferSize = $bufferLength;
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
        socket_bind($this->master, $addr, $port)                      or die("Failed: socket_bind()");
        socket_listen($this->master,1024)                             or die("Failed: socket_listen()");
        socket_set_nonblock($this->master)                                        or die("Failed: socket_set_nonblock()");
        $this->sockets['m'] = $this->master;
        $this->stdout("Server started\nListening on: $addr:$port\nMaster socket: ".$this->master);
    }

/*
    abstract protected function onmessage(&$user,$message); // Called immediately when the data is recieved. 
    abstract protected function onopen(&$user);        // Called after the handshake response is sent to the client.
    abstract protected function onclose($user);           // Called after the connection is closed.
    abstract public    function run();                   // event loop within trait in eventloop.php support now (socket,libev)
    abstract protected function addWritewatchers(&$user,$open);
*/

    public function registerHandler($handleName, $callable)
    {
        $route = null; // Will be implemented soon, but not this release.
        $handler[$handleName][] = array(
            'callable' => $callable,
            'route' => $route,
        );
    }

    private function dispatchEvent($event, $arguments = array())
    {

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
            if ($user->handshaked) { // prevent sending data to user who have not complete the handshake session.
                if ($vhost ==($user->headers['host'].$user->headers['get'])) {
                    $this->stdout("same channel ".$channel,true);
                    $this->ws_write($user, $message);
                }
=======
namespace Phpws\Core\Server;

abstract class Server implements \Phpws\Interfaces\WebsocketServer {

  // Configuration Start 
  protected $debug_mode = false; // debug tool I left in code. verbose mode
  protected $max_request_handshake_size  = 1024; // chrome : ~503B firefox : ~530B IE 11 : ~297B
  // There is no way to use http status code to send some application error to client we MUST open the connection first
  protected $max_client = 100;  // 1024 is the max with select() keep space for rejecting socket I suggest keeping 24
  protected $error_maxclien  = "WS SERVER reach it maximum limit. Please try again later"; // Set the error message sent to client.
  protected $headerOriginRequired  = false;
  protected $headerProtocolRequired = false;
  protected $willSupportExtensions = false;  // Turn it to true if you support any extensions

  // TODO : these 2 variables will be used to protect OOM and dynamically set max_client based on mem allowed per user
  protected $max_writeBuffer = 49152; //48K out
  protected $max_readBuffer = 49152; //48K in
  // Configuration End
  
  protected $userClass = 'WebSocketUser'; // redefine this if you want a custom user class.  The custom user class should implement \Phpws\Interfaces\WebsocketUser.
  protected $maxBufferSize;        
  protected $master;
  protected $readers                         = array();
  protected $writers                        = null;
  protected $users                                = array();
  protected $interactive                          = true;
  protected $nbclient                             = 0;


  protected $mem;
   
  function __construct($addr, $port, $bufferLength = 16384 ) {
    $this->maxBufferSize = $bufferLength;
    $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
    socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
    socket_bind($this->master, $addr, $port)                      or die("Failed: socket_bind()");
    socket_listen($this->master,1024)                             or die("Failed: socket_listen()");
    socket_set_nonblock($this->master)							              or die("Failed: socket_set_nonblock()");
    $this->sockets['m'] = $this->master;
    $this->stdout("Server started\nListening on: $addr:$port\nMaster socket: ".$this->master);
  }



  protected function onmessage(&$user,$message) {

  }

  protected function onopen(&$user) {

  }

  protected function onclose($user) {

  }

  protected function addWriteWatchers(&$user,$open) {

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
      if ($user->handshaked) { // prevent sending data to user who have not complete the handshake session.
        if ($vhost ==($user->headers['host'].$user->headers['get'])) {
          $this->stdout("same channel ".$channel,true);
          $this->ws_write($user, $message);
        }
      }
    }
  }
   
  protected function send(&$user,$message) {
    //$this->stdout("> $message");
    $message = $this->frame($message);
    $this->ws_write($user, $message);
  }

  public function setEventListener($event, $callback) {

  }

  /**
   * Read callback on socket
   */

  protected function cb_read(&$user) {
    $socket=$user->socket;
    $numBytes = socket_recv($socket,$buffer,$this->maxBufferSize,0); 
    if ($numBytes === false) {
      $this->stdout('Socket error: ' . socket_strerror(socket_last_error($socket)));
    }
    elseif ($numBytes == 0) {
      $this->disconnect($user);
      $this->stdout("Client disconnected. TCP connection lost: " . $socket);
    } 
    else {
      if (!$user->handshaked) {
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
            if ($this->nbclient>$this->max_client) {
              $message = $this->frame($this->error_maxclient);
              $this->ws_write($user,$message);
              $this->disconnect($user);
            }
            else {
              //clear buffer & state
              $user->handlingPartialPacket=FALSE;
              $user->readBuffer='';
>>>>>>> 97b6a5d1c40e9935bb9f9a1adc1a89b04be233e4:core/server/server.php
            }
        }
    }
     
    protected function send(&$user,$message) {
        //$this->stdout("> $message");
        $message = $this->frame($message);
        $this->ws_write($user, $message);
    }

    /**
     * Read callback on socket
     */

    protected function cb_read(&$user) {
        $socket=$user->socket;
        $numBytes = socket_recv($socket,$buffer,$this->maxBufferSize,0); 
        if ($numBytes === false) {
            $this->stdout('Socket error: ' . socket_strerror(socket_last_error($socket)));
        }
        elseif ($numBytes == 0) {
            $this->disconnect($user);
            $this->stdout("Client disconnected. TCP connection lost: " . $socket);
        } 
        else {
            if (!$user->handshaked) {
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
                        if ($this->nbclient>$this->max_client) {
                            $message = $this->frame($this->error_maxclient);
                            $this->ws_write($user,$message);
                            $this->disconnect($user);
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
        $sent = socket_write($user->socket, $user->writeBuffer, $sizeBuffer);
        if ($sent<$sizeBuffer) {
            //adjust the buffer
            $user->writeBuffer=substr($user->writeBuffer,$sent);
            //set watcher to write mode
            if (!$user->writeNeeded) {
                $this->stdout(">>> Start write watchers",true);
    $user->writeNeeded=true;
                $this->addWriteWatchers($user,true);
            }
            $this->stdout(">>size msg $sizeBuffer sent $sent",true);
        }
        else {
            //clear buffer and remove write flag and watcher
            $user->writeBuffer='';
            if ($user->writeNeeded){
                $this->stdout("<<< Stop write watchers",true);
                $user->writeNeeded=false;
                $this->addWriteWatchers($user,false);
            }
        }
    }

    protected function showmem(){
        $t=memory_get_usage()-$this->mem;
        $this->stdout("mem total : ".memory_get_usage()." diff : ".$t,true);
    }

    protected function connect($socket) {
        $this->nbclient++;
        $user = new $this->userClass(uniqid('u'), $socket);
        $this->users[$user->id] = &$user;
        $this->connecting($user);
        return $user;
    }

    protected function disconnect(&$user, $triggerClosed = true) {  
        if ($user !== null) {
            $this->nbclient--;
            $this->stdout("Client disconnected. ".$user->socket);
            //remove data used by internal method
            //$this->readWatchers[$user->id]->stop();
            //free memory before sending variable to be garbage collected
            $this->readWatchers[$user->id]=null;
            unset($this->readWatchers[$user->id]);
                            
            if ($triggerClosed) {
                $this->dispatchEvent('onClose', array('user' => $user));
                //$this->onclose($user);
                socket_close($user->socket);
            }
            else {
                $message = $this->frame('', $user, 'close');
                $this->ws_write($user, $message);
            }
            //remove any data from writeWatchers too
            if ($user->writeNeeded) {
                addWriteWatchers($user,false);
            }
            $this->users[$user->id]=null;
            unset($this->users[$user->id]);
            $user=null;
            unset($user);
        }
    }

    protected function doHandshake($user, $buffer) {
        $magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
        $headers = array();
        $lines = explode("\r\n",$buffer);
        //protocol and extensions can be sent on multiple line.
        //$headers['sec-websocket-protocol']='';
        //$headers['sec-websocket-extensions']='';
        foreach ($lines as $line) {
            if (strpos($line,":") !== false) {
                $header = explode(":",$line,2);
                switch ($header) {
                    case 'sec-websocket-protocol':
                        $headers[strtolower(trim($header[0]))] .= trim($header[1]).', ';
                        break;
                    case 'sec-websocket-extensions':
                        $headers[strtolower(trim($header[0]))] .= trim($header[1]).'; ';
                        break;
                    default : $headers[strtolower(trim($header[0]))] = trim($header[1]);
                }
            }
            elseif (stripos($line,"get ") !== false) {
                preg_match("/GET (.*) HTTP/i", $buffer, $reqResource);
                $headers['get'] = trim($reqResource[1]);
            }
        }
        if (isset($headers['get'])) {
            $user->requestedResource = $headers['get'];
        } 
        else {
            // todo: fail the connection
            $handshakeResponse = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";     
        }
        if (!isset($headers['host']) || !$this->checkHost($headers['host'])) {
            $handshakeResponse = "HTTP/1.1 400 Bad Request";
        }
        if (!isset($headers['upgrade']) || strtolower($headers['upgrade']) != 'websocket') {
            $handshakeResponse = "HTTP/1.1 400 Bad Request";
        } 
        if (!isset($headers['connection']) || strpos(strtolower($headers['connection']), 'upgrade') === FALSE) {
            $handshakeResponse = "HTTP/1.1 400 Bad Request";
        }
        if (!isset($headers['sec-websocket-key'])) {
            $handshakeResponse = "HTTP/1.1 400 Bad Request";
        } 
        if (!isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13) {
            $handshakeResponse = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
        }
        if (($this->headerOriginRequired && !isset($headers['origin']) ) || ($this->headerOriginRequired && !$this->checkOrigin($headers['origin']))) {
            $handshakeResponse = "HTTP/1.1 403 Forbidden";
        }
        
        // Protocol work on message level. So you can enforce it
        $protocol= $this->checkProtocol(explode(', ',$headers['sec-websocket-protocol']));
        if (($this->headerProtocolRequired && !isset($headers['sec-websocket-protocol'])) || ($this->headerProtocolRequired && !$protocol)) {
            $handshakeResponse = "HTTP/1.1 400 Bad Request";
        }
        else if ($protocol){
            $user->headers["protocol"] = $protocol;
            $subProtocol="Sec-WebSocket-Protocol: ".$protocol."\r\n";
        }
        
        // Done verifying the _required_ headers and optionally required headers.

        if (isset($handshakeResponse)) {
            $this->ws_write($user,$handshakeResponse);
            $this->disconnect($user);
            return;
        }

        // Keeping only relevant token that can be of use after handshake
        // protocol get host extensions
        $user->headers["get"] = $headers["get"];
        $user->headers["host"] = $headers["host"];

        // Not all browser support same extensions.
        // extensions work on frame level
        $extensionslist = $this->checkExtensions(explode('; ',$headers['sec-websocket-extensions']));
        if ($this->willSupportExtensions && !$extensions) {
            $user->headers["extensions"] = $extensionslist;
            $extensions = "Sec-WebSocket-Extensions: ".$extensionslist."\r\n";
        }

        $user->handshaked = TRUE;

        $webSocketKeyHash = sha1($headers['sec-websocket-key'] . $magicGUID);

        $rawToken = "";
        for ($i = 0; $i < 20; $i++) {
            $rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
        }
        $handshakeToken = base64_encode($rawToken) . "\r\n";

        $handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";
        $this->ws_write($user,$handshakeResponse);
        $this->dispatchEvent('onOpen', array('user' => $user));
        //$this->onopen($user);
    }

    protected function checkHost($hostName) {
        return true; // Override and return false if the host is not one that you would expect.
                                 // Ex: You only want to accept hosts from the my-domain.com domain,
                                 // but you receive a host from malicious-site.com instead.
    }

    protected function checkOrigin($origin) {
        return true; // Override and return false if the origin is not one that you would expect.
    }

    protected function checkExtensions($extensions) {
        // TODO : add extensions support. you can support multiple extensions but right now the list supported by
        // browser is very thin.
        return false; // Override and return name of extensions supported on server.
    }

    protected function checkProtocol($protocols) {
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

    protected function frame($message, $messageType='text', $messageContinues=false) {
        switch ($messageType) {
            case 'continuous': $bytes[1] = 0;
                break;
            case 'text': $bytes[1] = ($messageContinues) ? 0 : 1;
                break;
            case 'binary': $bytes[1] = ($messageContinues) ? 0 : 2;
                break;
            case 'close': $bytes[1] = 8;
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
        elseif ($length <= 65536) {
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
    protected function split_packet($length,$packet,$user) {
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
            $this->stdout("packet size : ".$length." frame #".$frame_id." position : ".$frame_pos." msglen : ".$headers['length'].
                " offset : ".$headers['offset']." = framesize of ".($headers['offset']+$headers['length']),true);

            //check if we deal with incomplete frame within packet.
            if ($headers['offset']+$headers['length'] > $length-$frame_pos) {
                $user->handlingPartialPacket = true;
                $user->readBuffer = $packet;
                break;
            }
            else if (($message = $this->deframe($packet, $user,$headers)) !== FALSE) {
                if ($user->hasSentClose) {
                    $this->disconnect($user);
                } else {
                    if (preg_match('//u', $message)) {
                        //$this->stdout("Is UTF-8\n".$message);
                        $this->dispatchEvent('onMessage', array('user' => $user, 'message' => $message));
                        //$this->onmessage($user, $message);
                    } else {
                        $this->stdout("not UTF-8\n",true);
                    }
                }
            }   
            //get the new position also modify packet data
            $frame_pos+=$headers['offset']+$headers['length'];
            $packet=substr($fullpacket,$frame_pos);
            $frame_id++;
        }
        $this->stdout("########    PACKET END         #########",true);
    }

    protected function deframe($packet, &$user,$headers) {
        // echo $this->strtohex($message);
        // get payload as now we are sure are completely sent
        $payload=substr($packet,$headers['offset'],$headers['length']);

        $pongReply = false;
        $willClose = false;
        switch($headers['opcode']) {
            case 0:  $this->stdout("chrome fragmenting payload over 128K.",true);
            case 1:
            case 2:
                break;
            case 8:
                // todo: close the connection
    $user->hasSentClose = true;
                return "";
            case 9:
                $pongReply = true;
            case 10:
    //A Pong frame MAY be sent unsolicited. This serves as a unidirectional
                //heartbeat. A response to an unsolicited Pong frame is not expected.
    //IE 11 default behavior send PONG ~30sec between them. Just ignore them.
                return false;  
            default:
                //$this->disconnect($user); // todo: fail connection
                $willClose = true;
                break;
        }

        if ($this->checkRSVBits($headers,$user)) {
            return false;
        }

        if ($willClose) {
            // todo: fail the connection
            return false;
        }
 
        if ($pongReply) {
            $reply = $this->frame($payload,$user,'pong');
            $this->ws_write($user,$reply);
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

    protected function extractHeaders($message) {
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
        //echo $this->strtohex($message);
        //$this->printHeaders($header);
        return $header;
    }

    protected function applyMask($headers,$payload) {
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
            //$this->disconnect($user); // todo: fail connection
            return true;
        }
        return false;
    }

    protected function strtohex($str) {
        $strout = "";
        for ($i = 0; $i < strlen($str); $i++) {
            $strout .= (ord($str[$i])<16) ? "0" . dechex(ord($str[$i])) : dechex(ord($str[$i]));
            $strout .= " ";
            if ($i%32 == 7) {
                $strout .= ": ";
            }
            if ($i%32 == 15) {
                $strout .= ": ";
            }
            if ($i%32 == 23) {
                $strout .= ": ";
            }
            if ($i%32 == 31) {
                $strout .= "\n";
            }
        }
        return $strout . "\n";
    }

    protected function getrps($start = false,$source = '') {
        if (!$start) {
            return microtime(true);
        }
        else {
            $end = microtime(true);
            $time = $end-$start;
            $rps = floor(1/$time);
            $this->stdout("$source time $time -- $rps prps",true); // prps = potential requests per second
            $this->showmem();
        }

    }

    protected function printHeaders($headers) {
        echo "Array\n(\n";
        foreach ($headers as $key => $value) {
            if ($key == 'length' || $key == 'opcode') {
                echo "\t[$key] => $value\n\n";
            } 
            else {
                echo "\t[$key] => ".$this->strtohex($value)."\n";

            }

        }
        echo ")\n";
    }
}
