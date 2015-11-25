<?php 
namespace Gpws\Eventloop;

// Default method 
trait Socket {
  public function run() {
    $this->mem = memory_get_usage();
    $this->stdout("RUNNING with select() method default");
    while(true) {
      if (empty($this->readWatchers)) {
        $this->readers['m'] = $this->master;
      }
      $read = $this->readers;
      $write = $this->writers;
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

  protected function addWriteWatchers(&$user,$open) {
    if ($open) {
      $this->writeWatchers[$user->id]=$user->socket;
    }
    else {
      $this->writeWatchers[$user->id]=null;
      unset($this->writeWatchers[$user->id]);   
    }
  }

  protected function getUserBySocket($socket) {
    foreach ($this->users as $user) {
      if ($user->socket == $socket) {
        return $user;
      }
    }
    return null;
  }
}

