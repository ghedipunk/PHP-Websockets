<?php

namespace Gpws\Interfaces;

interface ConnectionFactory{

    /**
     * @param resource $connection
     * @return WebsocketConnection
     */
    public function createConnection($connection);
}