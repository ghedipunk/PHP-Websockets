<?php

//require_once('./daemonize.php');
require_once('./users.php');

abstract class WebSocketServer {

	protected $userClass = 'WebSocketUser'; // redefine this if you want a custom user class.  The custom user class must inherit from websocketUser.
	protected $maxBufferSize;        
	protected $master;
	protected $sockets     = array();
	protected $users       = array();
	protected $interactive = true;
	
	function __construct($addr, $port, $bufferLength = 2048) {
		$this->maxBufferSize = $bufferLength;
		$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
		socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
		socket_bind($this->master, $addr, $port)                      or die("Failed: socket_bind()");
		socket_listen($this->master,20)                               or die("Failed: socket_listen()");
		$this->sockets[] = $this->master;
		$this->stdout("Server started\nListening on: $addr:$port\nMaster socket: ".$this->master);
		
		while(true) {
			if (empty($this->sockets)) {
				$this->sockets[] = $master;
			}
			$read = $this->sockets;
			$write = $except = null;
			@socket_select($read,$write,$except,null);
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
							if ($message = $this->deframe($buffer,$user)) {
								$this->process($user,$message);
							} else {
								do {
									$numByte = @socket_recv($socket,$buffer,$this->maxBufferSize,MSG_PEEK);
									if ($numByte > 0) {
										$numByte = @socket_recv($socket,$buffer,$this->maxBufferSize,0);
										if ($message = $this->deframe($buffer,$user)) {
											$this->process($user,$message);
										}
									}
								} while($numByte > 0);
							}
						}
					}
				}
			}
		}
	}
	
	abstract protected function process($user,$message); // Calked immediately when the data is recieved. 
	abstract protected function connected($user);        // Called after the connection is established.
	abstract protected function closed($user);           // Called after the connection is closed.
	
	protected function send($user,$message) {
		//$this->stdout("> $message");
		$message = $this->frame($message,$user);
		socket_write($user->socket,$message,strlen($message));
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
			unset($this->users[$foundUser]);
			$this->users = array_values($this->users);
		}
		foreach ($this->sockets as $key => $sock) {
			if ($sock == $socket) {
				$foundSocket = $key;
				break;
			}
		}
		if ($foundSocket !== null) {
			unset($this->sockets[$foundSocket]);
			$this->sockets = array_values($this->sockets);
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
		//preg_match("/Sec-WebSocket-Protocol:(.*)\r\n/i", $buffer, $reqSecWebSocketProtocol);
		preg_match("/Sec-WebSocket-Version:(.*)\r\n/i", $buffer, $reqSecWebSocketVersion);
		preg_match("/Cookie:(.*)\r\n/i", $buffer, $reqRawCookies);
		
		$user->headers['resource'] = trim($reqResource[1]);
		$user->headers['host'] = trim($reqHost[1]);
		$user->headers['upgrade'] = trim($reqUpgrade[1]);
		$user->headers['connection'] = trim($reqConnection[1]);
		$user->headers['secWebSocketKey'] = trim($reqSecWebSocketKey[1]);
		$user->headers['origin'] = trim($reqOrigin[1]);
		//$user->headers['secWebSocketProtocol'] = trim($reqSecWebSocketProtocol[1]);
		$user->headers['secWebSocketVersion'] = trim($reqSecWebSocketVersion[1]);
		//$user->headers['cookie'] = trim($reqRawCookies[1]); // todo: parse the key/value pairs into an array.
		
		$user->handshake = $buffer;
		
		$webSocketKeyHash = sha1($user->headers['secWebSocketKey'] . $magicGUID);
		
		$rawToken = "";
		for ($i = 0; $i < 20; $i++) {
			$rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
		}
		$handshakeToken = base64_encode($rawToken);
		$handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken\r\n\r\n";
		socket_write($user->socket,$handshakeResponse,strlen($handshakeResponse));
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
	
	protected function stderr($message) {
		if ($this->interactive) {
			echo "$message\n";
		}
	}
	
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
			//$b1 += 0; a syntactically null statement... good for demonstrating that, yes, we're purposefully not setting the Final bit.
			$user->sendingContinuous = true;
		} else {
			$b1 += 128;
			$user->sendingContinuous = false;
		}
		
		$length = strlen($message);
		$lengthField = "";
		if ($length < 126) {
			$b2 = $length;
		} elseif ($length <= 65536) {
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
		} else {
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
	
	protected function deframe($message, $user) {
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
				$willClose = true;
				break;
			case 9:
				$pongReply = true;
			case 10:
				break;
			default:
				//$this->disconnect($user); // todo: fail connection
				$willClose = true;
				break;
		}

		if ($user->handlingPartialPacket) {
			$message = $user->partialBuffer . $message;
			$user->handlingPartialPacket = false;
			return $this->deframe($message, $user);
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
			socket_write($user->socket,$reply,strlen($reply));
			return false;
		}
		
		if ($headers['length'] > strlen($payload)) {
			$user->handlingPartialPacket = true;
			$user->partialBuffer = $message;
			return false;
		}
		
		$payload = $this->applyMask($headers,$payload);
		
		if ($headers['fin']) {
			$user->partialMessage = "";
			return $payload;
		}
		$user->partialMessage = $payload;
		return false;
	}
	
	protected function extractHeaders($message) {
		$header = array('fin'     => $message[0] & chr(128),
						'rsv1'    => $message[0] & chr(64),
						'rsv2'    => $message[0] & chr(32),
						'rsv3'    => $message[0] & chr(16),
						'opcode'  => ord($message[0]) & 8 + ord($message[0]) & 4 + ord($message[0]) & 2 + ord($message[0]) & 1,
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
		} elseif ($header['length'] == 127) {
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
		} elseif ($header['hasmask']) {
			$header['mask'] = $message[2] . $message[3] . $message[4] . $message[5];
		}
		//echo $this->strtohex($message);
		//$this->printHeaders($header);
		return $header;
	}
	
	protected function extractPayload($message,$headers) {
		$offset = 2;
		if ($headers['hasmask']) {
			$offset += 4;
		}
		if ($headers['length'] > 65535) {
			$offset += 8;
		} elseif ($headers['length'] > 125) {
			$offset += 2;
		}
		return substr($message,$offset);
	}
	
	protected function applyMask($headers,$payload) {
		$effectiveMask = "";
		if ($headers['hasmask']) {
			$mask = $headers['mask'];
		} else {
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
	protected function checkRSVBits($headers,$user) { // override this method if you are using an extension where the RSV bits are used.
		if (ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0) {
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
	
	protected function printHeaders($headers) {
		echo "Array\n(\n";
		foreach ($headers as $key => $value) {
			if ($key == 'length' || $key == 'opcode') {
				echo "\t[$key] => $value\n\n";
			} else {
				echo "\t[$key] => ".$this->strtohex($value)."\n";
		
			}
			
		}
		echo ")\n";
	}
}