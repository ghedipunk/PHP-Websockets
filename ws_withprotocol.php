#!/usr/bin/env php
<?php

class echoServer extends core_websockets {
  // eventloop_[socket,libev] are the eventloop handler you can use. It's set to socket by default.
  // protocol_[echobot,broadcasting] json and msgpack are there for example ( you can use protocol folder or use it directly here ) 
  // extensions_ none implemented yet
  // toolbox_[yourcode] 
  use eventloop_socket,protocol_broadcasting,protocol_echobot;
  
  // Configuration Start ( default value uncomment if you need different value ) 
  //protected $debug_mode							= false; // debug tool I left in code. verbose mode
  //protected $max_request_handshake_size           = 1024; // chrome : ~503B firefox : ~530B IE 11 : ~297B 
  // There is no way to use http status code to send some application error to client we MUST open the connection first
  //protected $max_client                           = 100;  // 1024 is the max with select() keep space for rejecting socket I suggest keeping 24
  //protected $error_maxclient   = "WS SERVER reach it maximum limit. Please try again later"; // Set the error message sent to client. 
  //protected $headerOriginRequired                 = false;
  //protected $headerProtocolRequired               = false;
  //protected $willSupportExtensions                = false;  // Turn it to true if you support any extensions

  // TODO : these 2 variables will be used to protect OOM and dynamically set max_client based on mem allowed per user
  //protected $max_writeBuffer					    = 49152; //48K out 
  //protected $max_readBuffer					    = 49152; //48K in 
  // Configuration End
  

  //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.
  
  protected function onmessage (&$user, $message) {
    if ($user->headers['protocol']) {
      switch($user->headers['protocol']) {
        case 'json': //$parsed = json_decode($message); // example of parsing method to be process later
          break;
        case 'msgpack': //$parsed = msgpack_unpack($message); // example of parsing method to be process later
          break;
        case 'broadcasting': $this->broadcasting($user,$message); // example of standalone method
          return;
        case 'echobot': $this->echobot($user,$message); // another example of standalone method
          return;
      }
      // do some code after parsing method use
      $this->send($user,$message); 
    }
    else { 
      // Fallback if client specified none protocol
      // This part and if ($user->headers['protocol']) is pointless if the server $headerProtocolRequired set TRUE
      // because the connection will fail anyway.
      $this->broadcast($message,$user);
    }
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

  // Overide the method in this file protect the need to reaply this on core/websockets if we update the file.
  // Also easier to read
  protected function checkProtocol($protocols) {
    // Note : If the client specified subprotocol you MUST choose ONLY ONE  of the list otherwise 
    // the client will close the connection.  It's FIFO order sent by client.
    foreach($protocols as $subprotocol ) {
      switch ($subprotocol) {
        //the list of protocol the server will support
        case "json":
        case "msgpack";
        case "echobot";
        case "broadcasting";
          return $subprotocol;
      }
    }
    return false;
  }
}

$echo = new echoServer("0.0.0.0","9000"); 

$echo->run();
