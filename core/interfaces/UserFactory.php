<?php

namespace Gpws\Interfaces;

interface UserFactory{

    /**
     * @param resource $connection
     * @return WebsocketUser
     */
    public function createUser($connection);
}