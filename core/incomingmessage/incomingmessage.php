<?php

namespace Gpws\Core;

use Gpws\Interfaces\Deframer;

class IncomingMessage implements \Gpws\Interfaces\IncomingMessage {

    public function __construct(Deframer $deframer) {
        $this->deframer = $deframer;
    }

    public function addBytes($bytes) {
        // TODO: Implement addBytes() method.
    }

    public function getMessage() {
        // TODO: Implement getMessage() method.
    }

    public function getSender() {
        // TODO: Implement getSender() method.
    }

    /** @var Deframer */
    protected $deframer;
}