<?php

//require_once('./daemonize.php');
require_once('./users.php');

abstract class WebSocketServer {

  protected $userClass = 'WebSocketUser'; // redefine this if you want a custom user class.  The custom user class should inherit from WebSocketUser.
  protected $maxBufferSize;
  protected $master;
  protected $listenAddress;
  protected $listenPort;
  protected $sockets                              = array();
  protected $users                                = array();
  protected $heldMessages                         = array();
  protected $interactive                          = true;
  protected $headerOriginRequired                 = false;
  protected $headerSecWebSocketProtocolRequired   = false;
  protected $headerSecWebSocketExtensionsRequired = false;

  function __construct($addr, $port, $bufferLength = 2048) {
    $this->maxBufferSize = $bufferLength;
    $this->listenAddress = $addr;
    $this->listenPort = $port;

    $this->setupConnection();
    $this->stdout("Server started\nListening on: $addr:$port\nMaster socket: ".$this->master);
  }

  abstract protected function process($user,$message); // Called immediately when the data is recieved. 
  abstract protected function connected($user);        // Called after the handshake response is sent to the client.
  abstract protected function closed($user);           // Called after the connection is closed.

  /**
   * @return void
   */
  protected function setupConnection() {
    $errno = $errstr = null;

    $this->master = stream_socket_server(
        'tcp://' . $this->listenAddress . ':' . $this->listenPort,
        $errno,
        $errstr,
        STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
    );
    // TODO: (long before merge to master) error checking
  }

  /**
   * @param WebSocketUser $user
   *
   * @return void
   */
  protected function connecting($user) {
    // Override to handle a connecting user, after the instance of the User is created, but before
    // the handshake has completed.
  }

  /**
   * @param WebSocketUser $user
   * @param string $message
   *
   * return void
   */
  protected function send($user, $message) {
    if ($user->handshake) {
      $message = $this->frame($message,$user);
      $result = @fwrite($user->socket, $message, strlen($message));
    }
    else {
      // User has not yet performed their handshake.  Store for sending later.
      $holdingMessage = array('user' => $user, 'message' => $message);
      $this->heldMessages[] = $holdingMessage;
    }
  }

  /**
   * @return void
   */
  protected function tick() {
    // Override this for any process that should happen periodically.  Will happen at least once
    // per second, but possibly more often.
  }

  /**
   * @return void
   */
  protected function _tick() {
    // Core maintenance processes, such as retrying failed messages.
    foreach ($this->heldMessages as $key => $hm) {
      $found = false;
      foreach ($this->users as $currentUser) {
        if ($hm['user']->socket == $currentUser->socket) {
          $found = true;
          if ($currentUser->handshake) {
            unset($this->heldMessages[$key]);
            $this->send($currentUser, $hm['message']);
          }
        }
      }
      if (!$found) {
        // If they're no longer in the list of connected users, drop the message.
        unset($this->heldMessages[$key]);
      }
    }
  }

  /**
   * Main processing loop
   *
   * @return void
   */
  public function run() {
    while(true) {

      $read = $this->sockets;
      $read[] = $this->master;
      $write = $except = null;
      $this->_tick();
      $this->tick();

      stream_select($read, $write, $except, 1);
      foreach ($read as $socket) {
        if ($socket == $this->master) {
          if ($newConnection = stream_socket_accept($this->master, 0)) {
            $this->connect($newConnection);
            $this->stdout("Client connected. " . $newConnection);
          }
          break; // Breaks out of foreach.
        }

        $buffer = fread($socket, $this->maxBufferSize);
        $numBytes = strlen($buffer);
        if ($numBytes == 0) {
          $this->disconnect($socket);
          $this->stderr("Client disconnected. TCP connection lost: " . $socket);
        }
        else {
          $user = $this->getUserBySocket($socket);
          if (!$user->handshake) {
            $tmp = str_replace("\r", '', $buffer);
            if (strpos($tmp, "\n\n") === false) {
              continue; // If the client has not finished sending the header, then wait before sending our upgrade response.
            }
            $this->doHandshake($user, $buffer);
          } else {
            //split packet into frame and send it to deframe
            $this->split_packet($numBytes, $buffer, $user);
          }
        }
      }
    }
  }

  /**
   * @param resource $socket
   */
  protected function connect($socket) {
    $user = new $this->userClass(uniqid('u'), $socket); // Todo: Check PHP 7 compatibility.  I believe that the parse order will break this.
    $this->users[$user->id] = $user;
    $this->sockets[$user->id] = $socket;
    $this->connecting($user);
  }

  /**
   * @param resource $socket
   * @param bool|true $triggerClosed
   * @param int|null $sockErrNo
   *
   * @return void
   */
  protected function disconnect($socket, $triggerClosed = true, $sockErrNo = null) {
    $disconnectedUser = $this->getUserBySocket($socket);
    
    if ($disconnectedUser !== null) {
      unset($this->users[$disconnectedUser->id]);
        
      if (array_key_exists($disconnectedUser->id, $this->sockets)) {
        unset($this->sockets[$disconnectedUser->id]);
      }
      
      if ($triggerClosed) {
        $this->closed($disconnectedUser);
        fclose($disconnectedUser->socket);
      }
      else {
        $message = $this->frame('', $disconnectedUser, 'close');
        @fwrite($disconnectedUser->socket, $message, strlen($message));
      }
    }
  }

  /**
   * @param WebSocketUser $user
   * @param string $buffer
   *
   * @return void
   */
  protected function doHandshake($user, $buffer) {
    $magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
    $headers = array();
    $lines = explode("\n",$buffer);
    foreach ($lines as $line) {
      if (strpos($line,":") !== false) {
        $header = explode(":",$line,2);
        $headers[strtolower(trim($header[0]))] = trim($header[1]);
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
    else {

    }
    if (!isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13) {
      $handshakeResponse = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
    }
    if (($this->headerOriginRequired && !isset($headers['origin']) ) || ($this->headerOriginRequired && !$this->checkOrigin($headers['origin']))) {
      $handshakeResponse = "HTTP/1.1 403 Forbidden";
    }
    if (($this->headerSecWebSocketProtocolRequired && !isset($headers['sec-websocket-protocol'])) || ($this->headerSecWebSocketProtocolRequired && !$this->checkWebsocProtocol($headers['sec-websocket-protocol']))) {
      $handshakeResponse = "HTTP/1.1 400 Bad Request";
    }
    if (($this->headerSecWebSocketExtensionsRequired && !isset($headers['sec-websocket-extensions'])) || ($this->headerSecWebSocketExtensionsRequired && !$this->checkWebsocExtensions($headers['sec-websocket-extensions']))) {
      $handshakeResponse = "HTTP/1.1 400 Bad Request";
    }

    // Done verifying the _required_ headers and optionally required headers.

    if (isset($handshakeResponse)) {
      fwrite($user->socket, $handshakeResponse, strlen($handshakeResponse));
      $this->disconnect($user->socket);
      return;
    }

    $user->headers = $headers;
    $user->handshake = $buffer;

    $webSocketKeyHash = sha1($headers['sec-websocket-key'] . $magicGUID);

    $rawToken = "";
    for ($i = 0; $i < 20; $i++) {
      $rawToken .= chr(hexdec(substr($webSocketKeyHash, $i*2, 2)));
    }
    $handshakeToken = base64_encode($rawToken) . "\r\n";

    $subProtocol = (isset($headers['sec-websocket-protocol'])) ? $this->processProtocol($headers['sec-websocket-protocol']) : "";
    $extensions = (isset($headers['sec-websocket-extensions'])) ? $this->processExtensions($headers['sec-websocket-extensions']) : "";

    $handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";
    fwrite($user->socket, $handshakeResponse, strlen($handshakeResponse));
    $this->connected($user);
  }

  /**
   * @param string $hostName
   *
   * @return bool
   */
  protected function checkHost($hostName) {
    return true; // Override and return false if the host is not one that you would expect.
                 // Ex: You only want to accept hosts from the my-domain.com domain,
                 // but you receive a host from malicious-site.com instead.
  }

  /**
   * @param string $origin
   *
   * @return bool
   */
  protected function checkOrigin($origin) {
    return true; // Override and return false if the origin is not one that you would expect.
  }

  /**
   * @param string $protocol
   *
   * @return bool
   */
  protected function checkWebsocProtocol($protocol) {
    return true; // Override and return false if a protocol is not found that you would expect.
  }

  /**
   * @param string $extensions
   *
   * @return bool
   */
  protected function checkWebsocExtensions($extensions) {
    return true; // Override and return false if an extension is not found that you would expect.
  }

  /**
   * @param string $protocol
   *
   * @return string
   */
  protected function processProtocol($protocol) {
    return ""; // return either "Sec-WebSocket-Protocol: SelectedProtocolFromClientList\r\n" or return an empty string.  
           // The carriage return/newline combo must appear at the end of a non-empty string, and must not
           // appear at the beginning of the string nor in an otherwise empty string, or it will be considered part of 
           // the response body, which will trigger an error in the client as it will not be formatted correctly.
  }

  /**
   * @param string $extensions
   *
   * @return string
   */
  protected function processExtensions($extensions) {
    return ""; // return either "Sec-WebSocket-Extensions: SelectedExtensions\r\n" or return an empty string.
  }

  /**
   * @param resource $socket
   *
   * @return WebSocketUser
   */
  protected function getUserBySocket($socket) {
    foreach ($this->users as $user) {
      if ($user->socket == $socket) {
        return $user;
      }
    }
    return null;
  }

  /**
   * @param string $message
   *
   * @return void
   */
  public function stdout($message) {
    if ($this->interactive) {
      echo $message . PHP_EOL;
    }
  }

  /**
   * @param string $message
   *
   * @return void
   */
  public function stderr($message) {
    if ($this->interactive) {
      echo $message . PHP_EOL;
    }
  }

  /**
   * @param string $message
   * @param WebSocketUser $user
   * @param string $messageType
   * @param bool|false $messageContinues
   *
   * @return string
   */
  protected function frame($message, $user, $messageType='text', $messageContinues=false) {
    switch ($messageType) {
      case 'continuous':
        $b1 = 0;
        break;
      case 'text':
        $b1 = ($user->sendingContinuous) ? 0 : 1;
        break;
      case 'binary':
        $b1 = ($user->sendingContinuous) ? 0 : 2;
        break;
      case 'close':
        $b1 = 8;
        break;
      case 'ping':
        $b1 = 9;
        break;
      case 'pong':
        $b1 = 10;
        break;
    }
    if ($messageContinues) {
      $user->sendingContinuous = true;
    } 
    else {
      $b1 += 128;
      $user->sendingContinuous = false;
    }

    $length = strlen($message);
    $lengthField = "";
    if ($length < 126) {
      $b2 = $length;
    } 
    elseif ($length <= 65536) {
      $b2 = 126;
      $hexLength = dechex($length);
      //$this->stdout("Hex Length: $hexLength");
      if (strlen($hexLength)%2 == 1) {
        $hexLength = '0' . $hexLength;
      } 
      $n = strlen($hexLength) - 2;

      for ($i = $n; $i >= 0; $i=$i-2) {
        $lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
      }
      while (strlen($lengthField) < 2) {
        $lengthField = chr(0) . $lengthField;
      }
    } 
    else {
      $b2 = 127;
      $hexLength = dechex($length);
      if (strlen($hexLength)%2 == 1) {
        $hexLength = '0' . $hexLength;
      } 
      $n = strlen($hexLength) - 2;

      for ($i = $n; $i >= 0; $i=$i-2) {
        $lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
      }
      while (strlen($lengthField) < 8) {
        $lengthField = chr(0) . $lengthField;
      }
    }

    return chr($b1) . chr($b2) . $lengthField . $message;
  }
  
  /**
   * Check packet if we have more than one frame and process each frame individually
   *
   * @param int $length
   * @param string $packet
   * @param WebSocketUser $user
   *
   * @return void
   */
  protected function split_packet($length, $packet, $user) {
    //add PartialPacket and calculate the new $length
    if ($user->handlingPartialPacket) {
      $packet = $user->partialBuffer . $packet;
      $user->handlingPartialPacket = false;
      $length = strlen($packet);
    }
    $fullpacket = $packet;
    $frame_pos = 0;
    $frame_id = 1;

    while ($frame_pos < $length) {
      $headers = $this->extractHeaders($packet);
      $headers_size = $this->calcoffset($headers);
      $framesize = $headers['length'] + $headers_size;
      
      //split frame from packet and process it
      $frame = substr($fullpacket, $frame_pos, $framesize);

      if (($message = $this->deframe($frame, $user,$headers)) !== FALSE) {
        if ($user->hasSentClose) {
          $this->disconnect($user->socket);
        } else {
          if (preg_match('//u', $message)) {
            //$this->stdout("Is UTF-8\n".$message); 
            $this->process($user, $message);
          } else {
            //$this->stderr("not UTF-8\n");
          }
        }
      } 
      //get the new position also modify packet data
      $frame_pos += $framesize;
      $packet = substr($fullpacket, $frame_pos);
      $frame_id++;
    }
  }

  /**
   * @param string $headers
   *
   * @return int
   */
  protected function calcoffset($headers) {
    $offset = 2;
    if ($headers['hasmask']) {
      $offset += 4;
    }
    if ($headers['length'] > 65535) {
      $offset += 8;
    } elseif ($headers['length'] > 125) {
      $offset += 2;
    }
    return $offset;
  }

  /**
   * @param string $message
   * @param WebSocketUser $user
   * @return bool|int|string
   */
  protected function deframe($message, &$user) {
    //echo $this->strtohex($message);
    $headers = $this->extractHeaders($message);
    $pongReply = false;
    $willClose = false;
    switch($headers['opcode']) {
      case 0:
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
        break;
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

    $payload = $user->partialMessage . $this->extractPayload($message,$headers);

    if ($pongReply) {
      $reply = $this->frame($payload,$user,'pong');
      fwrite($user->socket, $reply, strlen($reply));
      return false;
    }

    if (extension_loaded('mbstring')) {
      if ($headers['length'] > mb_strlen($this->applyMask($headers,$payload))) {
        $user->handlingPartialPacket = true;
        $user->partialBuffer = $message;
        return false;
      }
    } 
    else {
      if ($headers['length'] > strlen($this->applyMask($headers,$payload))) {
        $user->handlingPartialPacket = true;
        $user->partialBuffer = $message;
        return false;
      }
    }

    $payload = $this->applyMask($headers,$payload);

    if ($headers['fin']) {
      $user->partialMessage = "";
      return $payload;
    }
    $user->partialMessage = $payload;

    return false;
  }

  /**
   * @param string $message
   *
   * @return array
   */
  protected function extractHeaders($message) {
    $header = array('fin'     => $message[0] & chr(128),
            'rsv1'    => $message[0] & chr(64),
            'rsv2'    => $message[0] & chr(32),
            'rsv3'    => $message[0] & chr(16),
            'opcode'  => ord($message[0]) & 15,
            'hasmask' => $message[1] & chr(128),
            'length'  => 0,
            'mask'    => "");
    $header['length'] = (ord($message[1]) >= 128) ? ord($message[1]) - 128 : ord($message[1]);

    if ($header['length'] == 126) {
      if ($header['hasmask']) {
        $header['mask'] = $message[4] . $message[5] . $message[6] . $message[7];
      }
      $header['length'] = ord($message[2]) * 256 
                + ord($message[3]);
    } 
    elseif ($header['length'] == 127) {
      if ($header['hasmask']) {
        $header['mask'] = $message[10] . $message[11] . $message[12] . $message[13];
      }
      $header['length'] = ord($message[2]) * 65536 * 65536 * 65536 * 256 
                + ord($message[3]) * 65536 * 65536 * 65536
                + ord($message[4]) * 65536 * 65536 * 256
                + ord($message[5]) * 65536 * 65536
                + ord($message[6]) * 65536 * 256
                + ord($message[7]) * 65536 
                + ord($message[8]) * 256
                + ord($message[9]);
    } 
    elseif ($header['hasmask']) {
      $header['mask'] = $message[2] . $message[3] . $message[4] . $message[5];
    }
    //echo $this->strtohex($message);
    //$this->printHeaders($header);
    return $header;
  }

  /**
   * @param string $message
   * @param array $headers
   *
   * @return string
   */
  protected function extractPayload($message, $headers) {
    $offset = 2;
    if ($headers['hasmask']) {
      $offset += 4;
    }
    if ($headers['length'] > 65535) {
      $offset += 8;
    } 
    elseif ($headers['length'] > 125) {
      $offset += 2;
    }
    return substr($message,$offset);
  }

  /**
   * @param array $headers
   * @param string $payload
   *
   * @return string
   */
  protected function applyMask($headers, $payload) {
    $effectiveMask = "";
    if ($headers['hasmask']) {
      $mask = $headers['mask'];
    } 
    else {
      return $payload;
    }

    while (strlen($effectiveMask) < strlen($payload)) {
      $effectiveMask .= $mask;
    }
    while (strlen($effectiveMask) > strlen($payload)) {
      $effectiveMask = substr($effectiveMask,0,-1);
    }
    return $effectiveMask ^ $payload;
  }

  /**
   * @param array $headers
   * @param WebSocketUser $user
   *
   * @return bool
   */
  protected function checkRSVBits($headers, $user) { // override this method if you are using an extension where the RSV bits are used.
    if (ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0) {
      //$this->disconnect($user); // todo: fail connection
      return true;
    }
    return false;
  }

  /**
   * @param string $str
   *
   * @return string
   */
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

  /**
   * @param array $headers
   */
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
