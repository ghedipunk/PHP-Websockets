#!/usr/bin/env php
<?php

require_once('./websockets.php');

/**
 * Example of a websocket server instance, these classes will generally just inherit properties and methods of WebSocketServer
 */
class echoServer extends WebSocketServer 
{
    /**
     * Maximum buffer size (inherited)
     * @var integer
     */
    protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.

    /**
     * Process incoming messages (inherited)
     * @param  userClass $user    userClass instance of the user that sent the message
     * @param  string    $message Message the user sent
     * @return null
     */
    protected function process($user, $message) 
    {
        $this->send($user,$message);
    }

    /**
     * Handle new user connections (inherited)
     * @param  userClass $user userClass instance of the user that is connected
     * @return null
     */
    protected function connected($user) 
    {
        // Do nothing: This is just an echo server, there's no need to track the user.
        // However, if we did care about the users, we would probably have a cookie to
        // parse at this step, would be looking them up in permanent storage, etc.
    }

    /**
     * Handle user disconnection (inherited)
     * @param  userClass $user userClass instance of the disconnected user
     * @return null
     */
    protected function closed($user) 
    {
        // Do nothing: This is where cleanup would go, in case the user had any sort of
        // open files or other objects associated with them.  This runs after the socket 
        // has been closed, so there is no need to clean up the socket itself here.
    }
}

// Create new echoServer (Which would be pretty much like creating an instance of WebSocketServer but with both overriden and new methods)

$echo = new echoServer("0.0.0.0","9000");

try 
{
    // Also inherited from WebSocketServer
    $echo->run();
}
catch (Exception $e) 
{
    // Caught an exception? Let's handle it with the inherited WebSocketServer::stdout method
    $echo->stdout($e->getMessage());
}
