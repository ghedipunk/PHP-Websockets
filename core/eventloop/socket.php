<?php 
namespace Gpws\Eventloop;

use Gpws\Interfaces\EventLoop;
use Gpws\Interfaces\WebsocketUser;

class Socket implements EventLoop {

  public function __construct($cli) {
    $this->memUsage = 0;

    if(!($cli instanceof \Gpws\Interfaces\Cli)) {
      throw new \InvalidArgumentException('Constructor of \\Gpws\\Eventloop\\Socket expects the first argument to implement \\Gpws\\Interfaces\\Cli');
    }
    $this->cli = $cli;

    $this->read = array();
  }

  public function run() {
    $this->memUsage = memory_get_usage();
    $this->cli->stdout("Running with Socket event loop");

    while (true) {
      $read = array($this->master);
      foreach ($this->users as $user) {
        $read[] = $user->getConnection();
      }
      $write = null;
      $except = null;

      socket_select($read, $write, $except, 1);
    }
  }

  /*
  public function run() {
    $this->mem = memory_get_usage();
    $this->stdout("RUNNING with select() method default");
    while(true) {
      if (empty($this->readWatchers)) {
        $this->readers['m'] = $this->master;
      }
      $read = $this->readers;
      $write = null;
      $except = null;
      socket_select($read,$write,$except,null);

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
  */

  protected function getUserByConnection($Connection) {
    foreach ($this->users as $user) {
      if ($user->getConnection() == $Connection) {
        return $user;
      }
    }
    return null;
  }

  protected function getMessageType($message) {

  }

  /** @var int Amount of memory used */
  protected $memUsage;

  /** @var \Gpws\Interfaces\WebsocketUser[] */
  protected $users;

  /** @var resource */
  protected $master;

  /** @var \Gpws\Interfaces\Cli Provides access to useful CLI related functions in a way that is aware of whether there is a terminal to write to */
  protected $cli;


}

