<?php 

// Default method 
class eventloop_socket extends core_websockets {
  public function run($apps) {
    var_dump($apps);
    $this->apps=$apps;
    $this->mem = memory_get_usage();
    $this->stdout("RUNNING with socket_select() method");
    while(true) {
      if (empty($this->readWatchers)) {
        $this->readWatchers['m'] = $this->master;
      }
      $read = $this->readWatchers;
      $write = $this->writeWatchers;
      $except = null;
      socket_select($read,$write,$except,null);
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
          $client = socket_accept($socket);
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
    $master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
    socket_set_option($master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
    socket_bind($master, $addr, $port)                      or die("Failed: socket_bind()");
    socket_listen($master,1024)                             or die("Failed: socket_listen()");
    socket_set_nonblock($master)							              or die("Failed: socket_set_nonblock()");
    return $master;
  }

  protected function ws_write_t($handle,$buffer) {
     $size = strlen($buffer);
     $sent = socket_write($handle, $buffer,$this->maxBufferSize);
     return $sent;
  }

  protected function ws_read_t($socket,&$buffer,$maxBufferSize) {
     $numBytes = socket_recv($socket,$buffer,$maxBufferSize,0);
     return $numBytes;
  }
 
  protected function ws_close_t($handle) {
     //socket_close($handle);
     socket_shutdown($handle, 1);
  }

  
}

