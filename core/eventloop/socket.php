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
    $this->cli->stdout("Running with select() method default");

    while (true) {

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
  */

  protected function addWriteWatcher(&$user) {
    if (!($user instanceof WebsocketUser)) {
      throw new \InvalidArgumentException('User passed to Socket::addWriteWatcher must implement \\Gpws\\Interfaces\WebsocketUser');
    }
    $this->write[$user->getId()]=$user->getConnection();

  }

  protected function getUserByConnection($Connection) {
    foreach ($this->users as $user) {
      if ($user->socket == $socket) {
        return $user;
      }
    }
    return null;
  }

  /** @var int Amount of memory used */
  protected $memUsage;

  /** @var \Gpws\Interfaces\Cli Provides access to useful CLI related functions in a way that is aware of whether there is a terminal to write to */
  protected $cli;

  /** @var array */
  protected $read;

  /** @var array */
  protected $write;

  /** @var array */
  protected $except;

  /** @var  */
  protected $users;
}

