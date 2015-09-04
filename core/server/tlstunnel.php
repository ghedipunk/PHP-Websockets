<?php

/**
 *
 *
 */

namespace Phpws\core\server;

class TlsTunnel {
  private $status = false;


  public function getStatus() {
	return $this->status;
  }

  public function setStatus($status) {
    $this->status = $status;
  }

  public function deframe(\Phpws\Interfaces\Message $message) {

  }

  public function frame(\Phpws\Interfaces\Message $message) {

  }
}