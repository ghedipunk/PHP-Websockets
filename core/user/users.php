<?php
namespace Phpws\Core;

class WebSocketUser {

  public $socket;
  public $id;
  public $watcher;
  public $headers = NULL;
  public $handshaked = false;

  public $handlingPartialPacket = false;
  public $readBuffer = "";
  public $writeNeeded = false;
  public $writeBuffer = "";

  public $partialMessage = "";
  
  public $hasSentClose = false;

  function __construct($id, $socket) {
    $this->id = $id;
    $this->socket = $socket;
  }
}
