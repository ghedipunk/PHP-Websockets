<?php 
// another example of protocol 
trait protocol_broadcasting {
  function broadcasting(&$user,$message) {
    $this->broadcast($message,$user);
  }
}
