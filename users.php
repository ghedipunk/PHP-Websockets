<?php

/**
 * WebSocketUser class, used as an example for an userClass class, keep in mind that most of the properties of the instance are to be set only by WebSocketServer
 */
class WebSocketUser 
{
	/**
	 * Socket of the current user
	 * @var socket
	 */
	public $socket;

	/**
	 * ID of the current user
	 * @var string
	 */
	public $id;

	/**
	 * Headers that the user has sent
	 * @var array
	 */
	public $headers = array();

	/**
	 * Have we completed our handshake with this user?
	 * @var boolean
	 */
	public $handshake = false;

	/**
	 * Will we handle a partial packet on this user's next message?
	 * @var boolean
	 */
	public $handlingPartialPacket = false;

	/**
	 * Buffer that holds partial packets
	 * @var string
	 */
	public $partialBuffer = "";

	/**
	 * Are we sending continious messages to this user?
	 * @var boolean
	 */
	public $sendingContinuous = false;

	/**
	 * Buffer that holds partial messages
	 * @var string
	 */
	public $partialMessage = "";

	/**
	 * Will we be closing this user's send connection?
	 * @var boolean
	 */
	public $hasSentClose = false;

	/**
	 * Constructs an instance of WebSocketUser
	 * @param string $id     ID of the user (uniqid('u'))
	 * @param socket $socket Socket resource of this user
	 */
	function __construct($id, $socket) 
	{
		$this->id = $id;
		$this->socket = $socket;
	}
}