<?php 

// Default method 
class eventloop_stream extends core_websockets {
  public function run($apps) {
    var_dump($apps);
    $this->apps=$apps;
    $this->mem = memory_get_usage();
    $this->stdout("RUNNING with stream_select() method");
    while(true) {
      if (empty($this->readWatchers)) {
        $this->readWatchers['m'] = $this->master;
      }
      $read = $this->readWatchers;
      $write = $this->writeWatchers;
      $except = null;
      stream_select($read,$write,$except,null);
      //outgoing data -- sending buffered data to client
      if ($write) {
        foreach ($write as $socket) {
          $start=$this->getrps();
            $user = $this->getUserBySocket($socket);
            $this->ws_write($user);
          $this->getrps($start);
        }
      }
      //incoming data
      foreach ($read as $socket) {
        if ($socket == $this->master) {
          $client = stream_socket_accept($socket);
          if (!$client) {
            $this->stderr("Failed: socket_accept()");
            continue;
          } 
          else {
            $user=$this->connect($client);
            $this->readWatchers[$user->id]=$client;
            $this->stdout("Client #$this->nbclient connected. " . $client);
          }
        } 
        else {
          $start=$this->getrps();
            $user = $this->getUserBySocket($socket);
            $this->cb_read($user);
          $this->getrps($start);
        }
      }
    }
  }

  protected function addWriteWatchers(&$user) {
    $this->writeWatchers[$user->id]=$user->socket;
  }

  protected function removeWriteWatchers(&$user) {
    $this->writeWatchers[$user->id]=null;
    unset($this->writeWatchers[$user->id]);   
  }

  protected function getUserBySocket($socket) {
    foreach ($this->users as $user) {
      if ($user->socket == $socket) {
        return $user;
      }
    }
    return null;
  }

  protected function ws_server($addr,$port){
    $master = stream_socket_server("tcp://$addr:$port", $errno, $errstr)  or die("Failed: stream_socket_server()");;
    stream_set_blocking($master,false)							                      or die("Failed: stream_set_blocking()");
    return $master;
  }

  protected function ws_write_t($handle,$buffer) {
    $sent = fwrite($handle,$buffer,$this->maxBufferSize);
    return $sent;
  }

  protected function ws_read_t($socket,&$buffer,$maxBufferSize) {
     $buffer = fread($socket,$maxBufferSize);
     return strlen($buffer);
  }

  protected function ws_close_t($handle) {
    stream_socket_shutdown($handle,STREAM_SHUT_WR);
  }

}

