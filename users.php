<?php

class WebSocketUser {

  public $socket;
  public $id;
  public $headers = array();
  public $handshake = false;

  public $handlingPartialPacket = false;
  public $partialBuffer = "";

  public $sendingContinuous = false;
  public $partialMessage = "";
  
  public $hasSentClose = false;

  function __construct($id, $socket) {
    $this->id = $id;
    $this->socket = $socket;
  }
}

class MyUser extends WebSocketUser {
  public $myId;

  function __construct($id, $socket, $myId) {
    parent::__construct($id, $socket);
    $this->myId = $myId;
  }
}