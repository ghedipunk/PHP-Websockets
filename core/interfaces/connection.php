<?php

/**
 *
 */

namespace Gpws\Interfaces;

interface Connection {
    public function getResource();
    public function getId();
    public function processMessage();
}
