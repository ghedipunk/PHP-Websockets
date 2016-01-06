<?php
namespace Gpws\Core;

class WebSocketUser implements \Gpws\Interfaces\WebsocketUser {

  const STATE_CONNECTING = 0;
  const STATE_CONNECTED = 1;
  const STATE_DISCONNECTING = 2;
  const STATE_DISCONNECTED = 3;

  private $socket;
  private $id;
  private $headers;
  private $cookies;
  private $sessionId;
  private $connectionState = self::STATE_CONNECTING;
  private $tlsTunnel;

  private $handlingPartialPacket = false;

  private $hasSentClose = false;

  function __construct($id, $socket) {
    $this->id = $id;
    $this->socket = $socket;
  }

  public function getTLSTunnel() {
    return $this->tlsTunnel;
  }

  public function getTLSStatus() {
    return $this->tlsTunnel->getStatus();
  }

  public function getConnectionId() {
    return $this->id;
  }

  public function getSocket() {
    return $this->socket;
  }

  public function handleMessage($buffer) {
    if (!$this->connectionState < self::STATE_CONNECTED) {
      return $this->doHandshake($buffer);
    }
  }

  public function doHandshake($buffer) {
    static $handledBuffer = ''; // In case we have to do the handshake over multiple TCP packets
    $buffer = $handledBuffer . $buffer;

    // if there are no blank lines, then wait for more data.
    $lines = explode("\r\n", $buffer);
    if (!in_array('', $lines)) {
      $handledBuffer = $buffer;
      return true;
    }

    $magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
    $headers = array();
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

    $this->connectionState = self::STATE_CONNECTED;

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
}
