#!/usr/bin/env php
<?php

//a simple autoload 
// Structure of $class = directory_filename
function __autoload($class) {
    $class=str_replace('_',DIRECTORY_SEPARATOR,$class);
    require_once('./' . $class . '.php');
}

class echoServer extends core_websockets {
  // eventloop_[socket,libev] are the eventloop handler you can use. It's set to socket by default.
  // extensions_ none implemented yet
  // toolbox_[yourcode] 
  use eventloop_socket; 
  
  //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.
  
  protected function onmessage (&$user, $message) {
    $this->broadcast($message,$user);
    //$this->send($user,$message);
  }
  
  protected function onopen ($user) {
    // Do nothing: This is just an echo server, there's no need to track the user.
    // However, if we did care about the users, we would probably have a cookie to
    // parse at this step, would be looking them up in permanent storage, etc.
  }
  
  protected function onclose ($user) {
    // Do nothing: This is where cleanup would go, in case the user had any sort of
    // open files or other objects associated with them.  This runs after the socket 
    // has been closed, so there is no need to clean up the socket itself here.
  }
}

$echo = new echoServer("0.0.0.0","9000");
//


$echo->run();

/*
try {
  $echo->run();
}
catch (Exception $e) {
  $echo->stdout($e->getMessage());
}
*/
