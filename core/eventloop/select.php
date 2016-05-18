<?php 
namespace Gpws\Eventloop;

use Gpws\Interfaces\Cli;
use Gpws\Interfaces\EventLoop;
use Gpws\Interfaces\ConnectionFactory;
use Gpws\Interfaces\WebsocketConnection;

class Select implements EventLoop {

    public function __construct(Cli $cli, ConnectionFactory $connectionFactory) {
        $this->memUsage = 0;

        $this->cli = $cli;
        $this->connectionFactory = $connectionFactory;

        $this->connections = array();
        $this->resources = array();
    }

    public function run() {
        $this->memUsage = memory_get_usage();
        $this->cli->stdout("Running with Socket event loop");

        while (true) {
            $read = array($this->master);
            foreach ($this->connections as $connection) {
                $read[] = $connection->getResource();
            }
            $write = null;
            $except = null;

            socket_select($read, $write, $except, 1);
            foreach ($read as $activeResource) {
                if ($activeResource == $this->master) {
                    $this->acceptConnection();
                } else {
                    $this->acceptMessage($activeResource);
                }
            }
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

    /**
     * Accepts a new connection
     */
    protected function acceptConnection() {
        $newResource = socket_accept($this->master);
        $connection = $this->connectionFactory->createConnection($newResource);
        $this->resources[] = $newResource;
        $this->connections[] = $connection;
    }

    /**
     * @param $resource resource
     */
    protected function acceptMessage($resource) {
        $connection = $this->getConnectionByResource($resource);
        $this->processMessage($connection);
    }

    protected function processMessage(WebsocketConnection $connection) {

    }

    protected function getConnectionByResource($resource) {
        foreach ($this->connections as $connection) {
            if ($connection->getResource() == $resource) {
                return $connection;
            }
        }
        return null;
    }

    protected function getMessageType($message) {

    }

    /** @var int Amount of memory used */
    protected $memUsage;

    /** @var \Gpws\Interfaces\WebsocketConnection[] */
    protected $connections;

    /** @var resource */
    protected $master;

    /** @var \Gpws\Interfaces\Cli Provides access to useful CLI related functions in a way that is aware of whether there is a terminal to write to */
    protected $cli;

    /** @var resource[] */
    protected $resources;

    /** @var ConnectionFactory */
    protected $connectionFactory;
}

