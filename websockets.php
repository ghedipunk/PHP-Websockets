<?php

/**
 * File where the WebSocketServer::$userClass is located
 */
require_once('./users.php');

/**
 * Class based on PHP sockets for Websocket-based applications, keep in mind that a lot of the methods of this class are to be inherited by classes that handle the actual instance of the server
 */
abstract class WebSocketServer 
{
	/**
	 * Name of the class that will hold information about each user connected to the server
	 * @var string
	 */
	protected $userClass = 'WebSocketUser'; // redefine this if you want a custom user class.The custom user class should inherit from WebSocketUser.

	/**
	 * Maximum size of the buffer
	 * @var integer
	 */
	protected $maxBufferSize; 

	/**
	 * Master socket, should only be handled by the WebSocketServer class
	 * @var Socket
	 */
	protected $master;

	/**
	 * Contains the sockets of all connected users in the server
	 * @var array
	 */
	protected $sockets = array();

	/**
	 * Contains all the userClass instances created when an user connects
	 * @var array
	 */
	protected $users = array();

	/**
	 * Array that contains messages that have not yet been sent (Eg. No handshake completed)
	 * @var array
	 */
	protected $heldMessages = array();

	/**
	 * Should we make the server interactive? (Eg. Allow the server to echo)
	 * @var boolean
	 */
	protected $interactive = true;

	/**
	 * Should we require an origin HTTP header when a new connection comes in?
	 * @var boolean
	 */
	protected $headerOriginRequired = false;

	/**
	 * Should we require a secondary protocol exclusive for handshakes? (Eg. Verify the server is willing to do a subprotocol)
	 * @var boolean
	 * @see https://tools.ietf.org/html/rfc6455#section-11.3.4 Explanation
	 */
	protected $headerSecWebSocketProtocolRequired = false;

	/**
	 * Should we require information about the protocol-level extensions that'll be used? 
	 * @var boolean
	 * @see https://tools.ietf.org/html/rfc6455#section-11.3.2 Explanation
	 */
	protected $headerSecWebSocketExtensionsRequired = false;

	/**
	 * Constructs the class, but does NOT run the server
	 * @param string   $addr         Address of the server
	 * @param integer  $port         Port of the server
	 * @param integer $bufferLength  Maximum buffer size
	 */
	function __construct($addr, $port, $bufferLength = 2048) 
	{
		$this->maxBufferSize = $bufferLength;

		$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)or die("Failed: socket_create()");

		socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
		socket_bind($this->master, $addr, $port)or die("Failed: socket_bind()");
		socket_listen($this->master,20) or die("Failed: socket_listen()");

		$this->sockets['m'] = $this->master;

		$this->stdout("Server started".PHP_EOL."Listening on: $addr:$port".PHP_EOL."Master socket: ".$this->master);
	}

	/**
	 * Called when data is received (Generally to only be used on classes that will inherit the function, such as the class that extends the example 'echoServer' class)
	 * @param  userClass $user     instance of the user that is sending the message
	 * @param  string    $message  Message that the user is sending
	 * @return null
	 */
	abstract protected function process($user, $message); // Called immediately when the data is recieved. (Generally to only be used on classes that will inherit the function, such as the class that extends the example 'echoServer' class)

	/**
	 * Called after the handshake from the server is sent to the client (user) (Generally to only be used on classes that will inherit the function, such as the class that extends the example 'echoServer' class)
	 * @param  userClass $user instance of the user that is requesting the handshake
	 * @return null
	 */
	abstract protected function connected($user);// Called after the handshake response is sent to the client.

	/**
	 * Called after an user has closed a connection (Generally to only be used on classes that will inherit the function, such as the class that extends the example 'echoServer' class)
	 * @param  userClass $user instance of the user that has closed the connection
	 * @return null
	 */
	abstract protected function closed($user); // Called after the connection is closed.

	/**
	 * Called after the creation of a new userClass instance but before handshake completion, override to handle user connections
	 * @param  userClass $user Instance of the new user
	 * @return null
	 */
	protected function connecting($user)
	{
		// Override to handle a connecting user, after the instance of the User is created, but before
		// the handshake has completed.
	}

	/**
	 * Writes a message to an user's socket connection, if a handshake has not been completed, it is kept on the WebSocketServer::heldMessages array
	 * @param  userClass $user    Instance of the user we'll be messaging
	 * @param  string    $message Message we'll be sending to the user
	 * @return null
	 */
	protected function send($user, $message)
	{
		if ( $user->handshake ) 
		{
			$message = $this->frame($message,$user);
			$result = @socket_write($user->socket, $message, strlen($message));
		}
		else 
		{
		// User has not yet performed their handshake.Store for sending later.

			$holdingMessage = array('user' => $user, 'message' => $message);
			$this->heldMessages[] = $holdingMessage;
		}
	}

	/**
	 * Tick method, generally called more than one time per second and can be overriden to run processes that'll be called periodically
	 * @return null
	 */
	protected function tick() 
	{
		// Override this for any process that should happen periodically.Will happen at least once
		// per second, but possibly more often.
	}

	/**
	 * Server tick method, not to be overriden. Retries for failed messages kept in the heldMessages array
	 * @return null
	 */
	protected function _tick() 
	{
		// Core maintenance processes, such as retrying failed messages.
		foreach ( $this->heldMessages as $key => $hm ) 
		{
			$found = false;

			foreach ( $this->users as $currentUser ) 
			{
				if ( $hm['user']->socket == $currentUser->socket ) 
				{
					$found = true;

					if ( $currentUser->handshake ) 
					{
						unset($this->heldMessages[$key]);
						$this->send($currentUser, $hm['message']);
					}
				}
			}

			if ( !$found ) 
			{
				// If they're no longer in the list of connected users, drop the message.
				unset($this->heldMessages[$key]);
			}
		}
	}

	/**
	 * Initilializes and runs the server (Main server loop)
	 * @return null
	 */
	public function run() 
	{
		while( true ) 
		{
			if ( empty($this->sockets) )
				$this->sockets['m'] = $this->master;

			$read = $this->sockets;
			$write = $except = null;

			$this->_tick();
			$this->tick();

			@socket_select($read, $write, $except, 1);

			foreach ( $read as $socket ) 
			{
				if ( $socket == $this->master ) 
				{
					$client = socket_accept($socket);

					if ( $client < 0 ) 
					{
						$this->stderr("Failed: socket_accept()");
						continue;
					} 
					else 
					{
						$this->connect($client);
						$this->stdout("Client connected. " . $client);
					}
				} 
				else 
				{
					$numBytes = @socket_recv($socket, $buffer, $this->maxBufferSize, 0);

					if ( $numBytes === false ) 
					{
						$sockErrNo = socket_last_error($socket);

						switch ($sockErrNo)
						{
							case 102: // ENETRESET-- Network dropped connection because of reset
							case 103: // ECONNABORTED -- Software caused connection abort
							case 104: // ECONNRESET -- Connection reset by peer
							case 108: // ESHUTDOWN-- Cannot send after transport endpoint shutdown -- probably more of an error on our part, if we're trying to write after the socket is closed.Probably not a critical error, though.
							case 110: // ETIMEDOUT-- Connection timed out
							case 111: // ECONNREFUSED -- Connection refused -- We shouldn't see this one, since we're listening... Still not a critical error.
							case 112: // EHOSTDOWN-- Host is down -- Again, we shouldn't see this, and again, not critical because it's just one connection and we still want to listen to/for others.
							case 113: // EHOSTUNREACH -- No route to host
							case 121: // EREMOTEIO-- Rempte I/O error -- Their hard drive just blew up.
							case 125: // ECANCELED-- Operation canceled
								$this->stderr("Unusual disconnect on socket " . $socket);
								$this->disconnect($socket, true, $sockErrNo); // disconnect before clearing error, in case someone with their own implementation wants to check for error conditions on the socket.
							break;
							default:
								$this->stderr('Socket error: ' . socket_strerror($sockErrNo));
						}

					}
					elseif ( $numBytes == 0 ) 
					{
						$this->disconnect($socket);
						$this->stderr("Client disconnected. TCP connection lost: " . $socket);
					} 
					else 
					{
						$user = $this->getUserBySocket($socket);

						if ( !$user->handshake ) 
						{
							$tmp = str_replace("\r", '', $buffer);

							if (strpos($tmp, "\n\n") === false ) 
								continue; // If the client has not finished sending the header, then wait before sending our upgrade response.

							$this->doHandshake($user, $buffer);
						} 
						else 
						{
							//split packet into frame and send it to deframe
							$this->split_packet($numBytes, $buffer, $user);
						}
					}
				}
			}
		}
	}

	/**
	 * Called when a new user connects to the server. Creates a new instance of userClass with the client's socket information
	 * @param  Socket $socket Socket of the new client
	 * @return null
	 */
	protected function connect($socket) 
	{
		$user = new $this->userClass(uniqid('u'), $socket);

		$this->users[$user->id] = $user;
		$this->sockets[$user->id] = $socket;

		$this->connecting($user);
	}

	/**
	 * Called when an user disconnects from the server
	 * @param  socket  $socket        Socket of the disconnected user
	 * @param  boolean $triggerClosed Did we trigger the closing of the connection?
	 * @param  integer $sockErrNo     If there was any, error code of the reason why the connection was closed.
	 * @return null
	 */
	protected function disconnect($socket, $triggerClosed = true, $sockErrNo = null) 
	{
		$disconnectedUser = $this->getUserBySocket($socket);

		if ( $disconnectedUser !== null ) 
		{
			unset($this->users[$disconnectedUser->id]);

			if ( array_key_exists($disconnectedUser->id, $this->sockets) ) 
				unset($this->sockets[$disconnectedUser->id]);

			if ( !is_null($sockErrNo) ) 
				socket_clear_error($socket);

			if ( $triggerClosed ) 
			{
				$this->closed($disconnectedUser);
				socket_close($disconnectedUser->socket);
			}
			else 
			{
				$message = $this->frame('', $disconnectedUser, 'close');
				@socket_write($disconnectedUser->socket, $message, strlen($message));
			}
		}
	}

	/**
	 * Performs a handshake with a new client
	 * @param  userClass $user   Instance of the user that is requesting the handshake
	 * @param  string    $buffer Content of the buffer of the user's socket
	 * @return null
	 * @todo                     Fail connection on unallowed resource retrieval method
	 */
	protected function doHandshake($user, $buffer) 
	{
		$magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

		$headers = array();

		$lines = explode("\n", $buffer);

		foreach ( $lines as $line ) 
		{
			if ( strpos($line, ":") !== false ) 
			{
				$header = explode(":",$line,2);
				$headers[strtolower(trim($header[0]))] = trim($header[1]);
			}
			elseif ( stripos($line,"get ") !== false ) 
			{
				preg_match("/GET (.*) HTTP/i", $buffer, $reqResource);
				$headers['get'] = trim($reqResource[1]);
			}
		}
		if ( isset($headers['get']) )
			$user->requestedResource = $headers['get'];
		else 
		{
		// todo: fail the connection
			$handshakeResponse = "HTTP/1.1 405 Method Not Allowed\r\n\r\n"; 
		}

		if ( !isset($headers['host']) || !$this->checkHost($headers['host']) )
			$handshakeResponse = "HTTP/1.1 400 Bad Request";

		if ( !isset($headers['upgrade']) || strtolower($headers['upgrade']) != 'websocket' )
			$handshakeResponse = "HTTP/1.1 400 Bad Request";

		if ( !isset($headers['connection']) || strpos(strtolower($headers['connection']), 'upgrade') === FALSE )
			$handshakeResponse = "HTTP/1.1 400 Bad Request";

		if ( !isset($headers['sec-websocket-key']) )
			$handshakeResponse = "HTTP/1.1 400 Bad Request";

		if ( !isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13 ) 
			$handshakeResponse = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";

		if ( ($this->headerOriginRequired && !isset($headers['origin']) ) || ($this->headerOriginRequired && !$this->checkOrigin($headers['origin'])) )
			$handshakeResponse = "HTTP/1.1 403 Forbidden";

		if ( ($this->headerSecWebSocketProtocolRequired && !isset($headers['sec-websocket-protocol'])) || ($this->headerSecWebSocketProtocolRequired && !$this->checkWebsocProtocol($headers['sec-websocket-protocol'])) )
			$handshakeResponse = "HTTP/1.1 400 Bad Request";

		if ( ($this->headerSecWebSocketExtensionsRequired && !isset($headers['sec-websocket-extensions'])) || ($this->headerSecWebSocketExtensionsRequired && !$this->checkWebsocExtensions($headers['sec-websocket-extensions'])) ) 
			$handshakeResponse = "HTTP/1.1 400 Bad Request";

		// Done verifying the _required_ headers and optionally required headers.

		if ( isset($handshakeResponse) ) 
		{
			socket_write($user->socket,$handshakeResponse,strlen($handshakeResponse));

			$this->disconnect($user->socket);

			return;
		}

		$user->headers = $headers;
		$user->handshake = $buffer;

		$webSocketKeyHash = sha1($headers['sec-websocket-key'] . $magicGUID);

		$rawToken = "";
		for ( $i = 0; $i < 20; $i++ ) 
		{
			$rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
		}

		$handshakeToken = base64_encode($rawToken) . "\r\n";

		$subProtocol = ( isset($headers['sec-websocket-protocol']) ) ? $this->processProtocol($headers['sec-websocket-protocol']) : "";
		$extensions = ( isset($headers['sec-websocket-extensions']) ) ? $this->processExtensions($headers['sec-websocket-extensions']) : "";

		$handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";

		socket_write($user->socket,$handshakeResponse,strlen($handshakeResponse));

		$this->connected($user);
	}

	/**
	 * Function called when we are performing a handshake, can be used to filter connections coming from certain hosts, override and return false if it's not what you would expect.
	 * @param  string $hostName Hostname (eg. malicious-site.com or my-domain.com)
	 * @return boolean
	 */
	protected function checkHost($hostName) 
	{
		return true; // Override and return false if the host is not one that you would expect.
		 // Ex: You only want to accept hosts from the my-domain.com domain,
		 // but you receive a host from malicious-site.com instead.
	}

	/**
	 * Function called when we are performing a handshake, can be used to check for the origin header, override and return false if it's not what you would expect.
	 * @param  string $origin Content of the origin header
	 * @return boolean
	 */
	protected function checkOrigin($origin) 
	{
		return true; // Override and return false if the origin is not one that you would expect.
	}

	/**
	 * Function called when we are performing a handshake, can be used to validate or filter certain protocols, override and return false if it's not what you would expect.
	 * @param  string $protocol Protocol the client is using
	 * @return boolean
	 */
	protected function checkWebsocProtocol($protocol) 
	{
		return true; // Override and return false if a protocol is not found that you would expect.
	}

	/**
	 * Function called when we are performing a handshake, can be used to validate or filter certain extensions, override and return false if it's not what you would expect.
	 * @param  string $extensions Extensions the client is using
	 * @return boolean
	 */
	protected function checkWebsocExtensions($extensions) 
	{
		return true; // Override and return false if an extension is not found that you would expect.
	}

	/**
	 * Function called when we are performing a handshake, can be used to process a protocol. Override and return either "Sec-WebSocket-Protocol: SelectedProtocolFromClientList\r\n" or return an empty string. The carriage return/newline combo must appear at the end of a non-empty string, and must not appear at the beginning of the string nor in an otherwise empty string, or it will be considered part of the response body, which will trigger an error in the client as it will not be formatted correctly.
	 * @param  string $protocol Protocol the client is using
	 * @return boolean
	 */
	protected function processProtocol($protocol) 
	{
		return ""; // return either "Sec-WebSocket-Protocol: SelectedProtocolFromClientList\r\n" or return an empty string.
		// The carriage return/newline combo must appear at the end of a non-empty string, and must not
		// appear at the beginning of the string nor in an otherwise empty string, or it will be considered part of 
		// the response body, which will trigger an error in the client as it will not be formatted correctly.
	}

	/**
	 * Function called when we are performing a handshake, can be used to process an extension. Override and return either "Sec-WebSocket-Extensions: SelectedExtensions\r\n" or return an empty string.
	 * @param  [type] $extensions [description]
	 * @return [type]             [description]
	 */
	protected function processExtensions($extensions) 
	{
		return ""; // return either "Sec-WebSocket-Extensions: SelectedExtensions\r\n" or return an empty string.
	}

	/**
	 * Returns the instance of userClass by using the socket connection
	 * @param  socket $socket Socket we'll be looking for
	 * @return mixed          Returns either an userClass instance or null if the user was not found
	 */
	protected function getUserBySocket($socket) 
	{
		foreach ( $this->users as $user ) 
		{
			if ( $user->socket == $socket )
				return $user;
		}

		return null;
	}

	/**
	 * Echoes text into the server if the interactive property is set to true, can be overriden to handle local server information printing
	 * @param  string $message Message to echo
	 * @return null
	 */
	public function stdout($message) 
	{
		if ( $this->interactive )
			sprintf("%s", $message);
	}

	/**
	 * Echoes an error into the server if the interactive property is set to true, can be overriden to handle local server error information printing
	 * @param  string $message Error to echo
	 * @return null
	 */
	public function stderr($message) 
	{
		if ( $this->interactive )
			sprintf("%s", $message);
	}

	/**
	 * Frames a message
	 * @param  string  	  $message          Message to frame
	 * @param  userClass  $user             User we'll be sending the framed message to
	 * @param  string     $messageType      Type of message (continous, text, binary, close, ping, pong)
	 * @param  boolean    $messageContinues Will the message be continious?
	 * @return string                       Returns the framed message
	 */
	protected function frame($message, $user, $messageType = 'text', $messageContinues = false) 
	{
		switch ($messageType) 
		{
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

		if ( $messageContinues )
			$user->sendingContinuous = true;
		else 
		{
			$b1 += 128;
			$user->sendingContinuous = false;
		}

		$length = strlen($message);

		$lengthField = "";

		if ( $length < 126 )
			$b2 = $length;
		elseif ( $length <= 65536 )
		{
			$b2 = 126;
			$hexLength = dechex($length);

			if ( strlen($hexLength)%2 == 1 )
				$hexLength = '0' . $hexLength;

			$n = strlen($hexLength) - 2;

			for ( $i = $n; $i >= 0; $i = $i-2 ) 
			{
				$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
			}

			while ( strlen($lengthField) < 2 ) 
			{
				$lengthField = chr(0) . $lengthField;
			}
		} 
		else 
		{
			$b2 = 127;
			$hexLength = dechex($length);

			if (strlen($hexLength)%2 == 1) 
				$hexLength = '0' . $hexLength;

			$n = strlen($hexLength) - 2;

			for ( $i = $n; $i >= 0; $i = $i-2 ) 
			{
				$lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
			}

			while ( strlen($lengthField) < 8 ) 
			{
				$lengthField = chr(0) . $lengthField;
			}
		}

		return chr($b1) . chr($b2) . $lengthField . $message;
	}

	//check packet if he have more than one frame and process each frame individually
	/**
	 * Splits a packet that contains multiple frames
	 * @param  integer $length Length of the packet
	 * @param  string  $packet Packet that we're splitting
	 * @param  userClass $user Instance of the userClass of the client that's sending the packet
	 * @return null
	 */
	protected function split_packet($length, $packet, $user) 
	{
		//add PartialPacket and calculate the new $length
		if ( $user->handlingPartialPacket ) 
		{
			$packet = $user->partialBuffer . $packet;
			$user->handlingPartialPacket = false;

			$length = strlen($packet);
		}

		$fullpacket = $packet;
		$frame_pos = 0;
		$frame_id = 1;

		while( $frame_pos < $length ) 
		{
			$headers = $this->extractHeaders($packet);
			$headers_size = $this->calcoffset($headers);

			$framesize = $headers['length'] + $headers_size;

			//split frame from packet and process it
			$frame = substr($fullpacket, $frame_pos, $framesize);

			if ( ($message = $this->deframe($frame, $user,$headers)) !== FALSE ) 
			{
				if ( $user->hasSentClose ) 
					$this->disconnect($user->socket);
				else 
				{
					if ( preg_match('//u', $message) ) 
						$this->process($user, $message);
					else 
						$this->stderr("not UTF-8");
				}
			} 

		//get the new position also modify packet data
			$frame_pos += $framesize;
			$packet = substr($fullpacket,$frame_pos);
			$frame_id++;
		}
	}

	/**
	 * Calculates the offset of the headers
	 * @param  array   $headers Header array
	 * @return integer 		    Offset of the headers
	 */
	protected function calcoffset($headers) 
	{
		$offset = 2;

		if ( $headers['hasmask'] )
			$offset += 4;

		if ( $headers['length'] > 65535 )
			$offset += 8;
		elseif ($headers['length'] > 125)
			$offset += 2;

		return $offset;
	}

	/**
	 * Removes the frame of a packet
	 * @param  string    $message Packet/message that we'll be deframing
	 * @param  userClass &$user   Reference to the userClass instance of the user that sent the message
	 * @return mixed			  Returns true if we were able to deframe the packet, if there's a fin header present, we'll return the payload
	 * @todo                      Close the connection on opcode 8 or unrecognized opcode
	 */
	protected function deframe($message, &$user) 
	{
		$headers = $this->extractHeaders($message);

		$pongReply = false;
		$willClose = false;

		switch($headers['opcode']) 
		{
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

		/* Deal by split_packet() as now deframe() do only one frame at a time.
		if ($user->handlingPartialPacket) {
		$message = $user->partialBuffer . $message;
		$user->handlingPartialPacket = false;
		return $this->deframe($message, $user);
		}
		*/

		if ( $this->checkRSVBits($headers,$user) ) 
			return false;

		if ( $willClose ) 
		{
			// todo: fail the connection
			return false;
		}

		$payload = $user->partialMessage . $this->extractPayload($message,$headers);

		if ( $pongReply ) 
		{
			$reply = $this->frame($payload,$user,'pong');
			socket_write($user->socket,$reply,strlen($reply));
			return false;
		}

		if ( extension_loaded('mbstring') ) 
		{
			if ( $headers['length'] > mb_strlen($this->applyMask($headers,$payload)) ) 
			{
				$user->handlingPartialPacket = true;
				$user->partialBuffer = $message;

				return false;
			}
		} 
		else 
		{
			if ( $headers['length'] > strlen($this->applyMask($headers,$payload)) ) 
			{
				$user->handlingPartialPacket = true;
				$user->partialBuffer = $message;

				return false;
			}
		}

		$payload = $this->applyMask($headers, $payload);

		if ( $headers['fin'] ) 
		{
			$user->partialMessage = "";
			return $payload;
		}

		$user->partialMessage = $payload;
		return false;
	}

	/**
	 * Extracts the header from a packet/message
	 * @param  array $message  Message that we'll be taking the headers from
	 * @return array           Array of all the headers the message has
	 */
	protected function extractHeaders($message) 
	{
		$header = array(
			'fin' => $message[0] & chr(128),
			'rsv1'=> $message[0] & chr(64),
			'rsv2'=> $message[0] & chr(32),
			'rsv3'=> $message[0] & chr(16),
			'opcode'=> ord($message[0]) & 15,
			'hasmask' => $message[1] & chr(128),
			'length'=> 0,
			'mask'=> "");

		$header['length'] = ( ord($message[1]) >= 128 ) ? ord($message[1]) - 128 : ord($message[1]);

		if ( $header['length'] == 126 ) 
		{
			if ( $header['hasmask'] )
				$header['mask'] = $message[4] . $message[5] . $message[6] . $message[7];

			$header['length'] = ord($message[2]) * 256 
			+ ord($message[3]);
		} 
		elseif ( $header['length'] == 127 ) 
		{
			if ( $header['hasmask'] )
				$header['mask'] = $message[10] . $message[11] . $message[12] . $message[13];

			$header['length'] = ord($message[2]) * 65536 * 65536 * 65536 * 256 
			+ ord($message[3]) * 65536 * 65536 * 65536
			+ ord($message[4]) * 65536 * 65536 * 256
			+ ord($message[5]) * 65536 * 65536
			+ ord($message[6]) * 65536 * 256
			+ ord($message[7]) * 65536 
			+ ord($message[8]) * 256
			+ ord($message[9]);
		} 
		elseif ( $header['hasmask'] ) 
			$header['mask'] = $message[2] . $message[3] . $message[4] . $message[5];

		return $header;
	}

	/**
	 * Extracts the payload from a message
	 * @param  string $message Message that we'll be getting the payload from
	 * @param  array  $headers Headers of the message
	 * @return string          Message payload
	 */
	protected function extractPayload($message, $headers) 
	{
		$offset = 2;

		if ( $headers['hasmask'] )
			$offset += 4;

		if ( $headers['length'] > 65535 )
			$offset += 8;
		elseif ( $headers['length'] > 125 )
			$offset += 2;

		return substr($message,$offset);
	}

	/**
	 * Applies a mask to a payload
	 * @param  array  $headers Headers that contain the mask
	 * @param  string $payload Payload we'll be masking
	 * @return string          Returns the original payload if the headers do not have a mask, if else returns the masked string
	 */
	protected function applyMask($headers, $payload) 
	{
		$effectiveMask = "";

		if ($headers['hasmask']) 
			$mask = $headers['mask'];
		else
			return $payload;

		while ( strlen($effectiveMask) < strlen($payload) ) 
		{
			$effectiveMask .= $mask;
		}

		while ( strlen($effectiveMask) > strlen($payload) ) 
		{
			$effectiveMask = substr($effectiveMask,0,-1);
		}

		return $effectiveMask ^ $payload;
	}

	/**
	 * Checks for RSVBits, override this method if you are using an extension where the RSV bits are used.
	 * @param  array     $headers  Header array
	 * @param  userClass $user     Instance of the userClass we'll be checking
	 * @return boolean			   Returns true if the RSVBits were valid, if else, returns false
	 * @todo 					   Fail user connection on successful checking
	 */
	protected function checkRSVBits($headers, $user) 
	{ // override this method if you are using an extension where the RSV bits are used.
		if ( ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0 ) 
		{
			//$this->disconnect($user); // todo: fail connection
			return true;
		}

		return false;
	}

	/**
	 * Converts a string to HEX
	 * @param  string $str String to convert
	 * @return string      Returns the converted string
	 */
	protected function strtohex($str) 
	{
		$strout = "";

		for ( $i = 0; $i < strlen($str); $i++ ) 
		{
			$strout .= ( ord($str[$i]) < 16 ) ? "0" . dechex(ord($str[$i])) : dechex(ord($str[$i]));
			$strout .= " ";

			if ( $i%32 == 7 ) 
				$strout .= ": ";

			if ( $i%32 == 15 ) 
				$strout .= ": ";

			if ( $i%32 == 23 ) 
				$strout .= ": ";

			if ( $i%32 == 31 ) 
				$strout .= "\n";
		}

		return $strout . "\n";
	}

	/**
	 * Prints the headers
	 * @param  array $headers  Headers to print
	 * @return null            Prints the headers
	 */
	protected function printHeaders($headers) 
	{
		echo "Array\n(\n";

		foreach ( $headers as $key => $value ) 
		{
			if ( $key == 'length' || $key == 'opcode' )
				echo "\t[$key] => $value\n\n";
			else
				echo "\t[$key] => ".$this->strtohex($value)."\n";
		}

		echo ")\n";
	}
}