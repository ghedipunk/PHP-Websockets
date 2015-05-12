#!/usr/bin/env php
<?php
//Same purpose of '@' doing it once
error_reporting(E_ERROR);  // only show error that will break the server.

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
  
  // Configuration Start ( default value uncomment if you need different value ) 
  //protected $debug_mode							              = false; // debug tool I left in code. verbose mode
  //protected $max_request_handshake_size           = 1024; // chrome : ~503B firefox : ~530B IE 11 : ~297B 
  // There is no way to use http status code to send some application error to client we MUST open the connection first
  //protected $max_client                           = 100;  // 1024 is the max with select() keep space for rejecting socket I suggest keeping 24
  //protected $error_maxclient   = "WS SERVER reach it maximum limit. Please try again later"; // Set the error message sent to client. 
  //protected $headerOriginRequired                 = false;
  //protected $headerProtocolRequired               = false;
  //protected $willSupportExtensions                = false;  // Turn it to true if you support any extensions

  // TODO : these 2 variables will be used to protect OOM and dynamically set max_client based on mem allowed per user
  //protected $max_writeBuffer					            = 49152; //48K out 
  //protected $max_readBuffer					              = 49152; //48K in 
  // Configuration End

  //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.
  
  protected function onmessage (&$user, $message) {
    $this->broadcast($message,$user); //broadcasting message to everyone 
    //$this->send($user,$message);   // sending only to current client
  }
  
  protected function onopen (&$user) {
    // Do nothing: This is just an echo server, there's no need to track the user.
    // However, if we did care about the users, we would probably have a cookie to
    // parse at this step, would be looking them up in permanent storage, etc.
    // Also you can use this for sending a welcome message to the newly client.
  }
  
  protected function onclose ($user) {
    // Do nothing: This is where cleanup would go, in case the user had any sort of
    // open files or other objects associated with them.  This runs after the socket 
    // has been closed, so there is no need to clean up the socket itself here.
  }
}

$echo = new echoServer("0.0.0.0","9000");

$echo->run();
