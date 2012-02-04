<?php

//require_once('daemonize.php');
require_once('users.php');

abstract class WebSocketServer {

	protected $userClass = 'websocketUser'; // redefine this if you want a custom user class.  The custom user class must inherit from websocketUser.
	protected $maxBufferSize = 2048;        // redefine this for a different buffer size.
	protected $master;
	protected $sockets     = array();
	protected $users       = array();
	protected $interactive = false;
	
	function __construct($addr, $port) {
		$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
		socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
		socket_bind($this->master, $addr, $port)                      or die("Failed: socket_bind()");
		socket_listen($this->master,20)                               or die("Failed: socket_listen()");
		$this->sockets[] = $this->master;
		$this->stdout("Server started\nListening on: $address:$port\nMaster socket: ".$this->master."";
		
		while(true) {
			$read = $this->sockets;
			$write = $except = null
			socket_select($read,$write,$except,null);
			foreach ($read as $socket) {
				if ($socket == $this->master) {
					$client = socket_accept($socket);
					if ($client < 0) {
						$this->stderr("Failed: socket_accept()");
						continue;
					} else {
						$this->connect($client);
					}
				} else {
					$numBytes = @socket_recv($socket,$buffer,$this->maxBufferSize,0); // todo: if($numBytes === false) { error handling } elseif ($numBytes === 0) { remote client disconected }
					if ($numBytes == 0) {
						$this->disconnect($socket);
					} else {
						$user = $this->getUserBySocket($socket);
						if (!$user->handshake) {
							$this->doHandshake($user,$buffer);
						} else {
							$this->process($user,$this->unwrap($buffer));
						}
					}
				}
			}
		}
	}
	
	abstract protected function process($user,$message); // Calked immediately when the data is recieved. 
	abstract protected function connected($user);        // Called after the connection is established.
	abstract protected function closed($user);           // Called after the connection is closed.
	
	protected function send($clientSocket,$message) {
		$this->stdout("> $message");
		$message = $this->wrap($message);
		socket_write($clientSocket,$message,strlen($message));
	}
	
	protected function connect($socket) {
		$user = new $this->userClass(uniqid(),$socket);
		array_push($this->users,$user);
		array_push($this->sockets,$socket);
		$this->connected($user);
	}

	protected function disconnect($socket) {
		$foundUser = null;
		$foundSocket = null;
		foreach ($this->users as $key => $user) {
			if ($user->socket == $socket) {
				$foundUser = $key;
				$disconnectedUser = $user;
				break;
			}
		}
		if ($foundUser !== null) {
			$this->users = array_values(unset($this->users[$foundUser]));
		}
		foreach ($this->sockets as $key => $sock) {
			if ($sock == $socket) {
				$foundSocket = $key;
				break;
			}
		}
		if ($foundSocket !== null) {
			$this->sockets = array_values(unset($this->users[$foundSocket));
		}
		$this->closed($disconnectedUser);
	}
	
	protected function doHandshake($user, $buffer) {
		$magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
		preg_match("/GET (.*) HTTP/i", $buffer, $reqResource);    // todo: set up dynamic classes based on this requested resource
		preg_match("/Host:(.*)\r\n/i", $buffer, $reqHost);
		preg_match("/Upgrade:(.*)\r\n/i", $buffer, $reqUpgrade); // should always be "websocket", although we'll leave it up to the implementer of the inheritting class to determin if this matters
		preg_match("/Connection:(.*)\r\n/i", $buffer, $reqConnection); // should always be "Upgrade", again leave it up to the implementer
		preg_match("/Sec-WebSocket-Key:(.*)\r\n/i", $buffer, $reqSecWebSocketKey);
		preg_match("/Origin:(.*)\r\n/i", $buffer, $reqOrigin);
		preg_match("/Sec-WebSocket-Protocol:(.*)\r\n/i", $buffer, $reqSecWebSocketProtocol);
		preg_match("/Sec-WebSocket-Version:(.*)\r\n/i", $buffer, $reqSecWebSocketVersion);
		preg_match("/Cookie:(.*)\r\n/i", $buffer, $reqRawCookies);
		
		$user->headers['resource'] = trim($reqResource[1]);
		$user->headers['host'] = trim($reqHost[1]);
		$user->headers['upgrade'] = trim($reqUpgrade[1]);
		$user->headers['connection'] = trim($reqConnection[1]);
		$user->headers['secWebSocketKey'] = trim($reqSecWebSocketKey[1]);
		$user->headers['origin'] = trim($reqOrigin[1]);
		$user->headers['secWebSocketProtocol'] = trim($reqSecWebSocketProtocol[1]);
		$user->headers['secWebSocketVersion'] = trim(reqSecWebSocketVersion[1]);
		$user->headers['cookie'] = trim($reqRawCookies[1]); // todo: parse the key/value pairs into an array.
		
		$webSocketKeyHash = sha1($user->headers['secWebSocketKey'] . $magicGUID);
		
		$rawToken = "";
		for ($i = 0; i < 20; i++) {
			$rawToken .= char(hexdec(substr($webSocketKeyHash,$i*2, 2)));
		}
		$handshakeToken = base64_encode($rawToken);
		$handshakeResponse = "HTTP/1.1 101 Switching Protocols\nUpgrade: websocket\nConnection: Upgrade\nSec-WebSocket-Accept: $handshakeToken\n\n";
		
	}
	
	protected function getUserBySocket($socket) {
		foreach ($this->users as $user) {
			if ($user->socket == $socket) {
				return $user;
			}
		}
		return null;
	}
	
	protected function stdout($message) {
		if ($this->interactive) {
			echo "$message\n";
		}
	}
	
	protected function wrap($message) {
		return chr(0) . $message . chr(255);
	}
	
	protected function unwrap($message) {
		return substr($message,1,-1);
	}
}