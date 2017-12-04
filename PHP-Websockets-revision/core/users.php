<?php

class WebSocketUser {
  const CONNECTING  = 0;
  const OPEN        = 1;
  const CLOSING     = 2;

  public $socket;
  public $id;
  public $watcher;
  public $headers = NULL;
  public $readystate = self::CONNECTING;
  public $handshaked = false;  //readystate will replace this variable

  public $handlingPartialPacket = false;
  public $readBuffer = "";
  public $writeNeeded = false;
  public $writeBuffer = "";
  public $opcode = false;
  public $fin = false;
  public $willclose = false;
 
  public $firstframeopcode= false;
  public $willcontinue = false;

  public $overflow="";
  public $partialMessage = "";
  
  public $hasSentClose = false;

  function __construct($id, $socket) {
    $this->id = $id;
    $this->socket = $socket;
  }
}

