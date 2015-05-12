<?php
namespace Phpws;

class EventLoop implements \Phpws\Interfaces\EventLoop
{
  private $maxBufferSize;
  private $listenAddress;
  private $port;
  private $maxConnectingQueue;
  private $masterSocket;
  private $connectionManager;

  private static $running = true;

  public function run()
  {
    $config = \Phpws\GlobalConfig::getSingleton();
    
    if (($factoryClass = $config->getValue('factory overrides', 'FactoryClass')) === null)
    {
      $factoryClass = '\Phpws\Factory';
    }

    // Set defaults
    $this->maxBufferSize = 2048;
    $this->listenAddress = "0.0.0.0";
    $this->port = 8086;
    $this->maxConnectingQueue = 20;

    // Initialize based on config, where not default.
    if (($maxBufferSize = $config->getValue('main', 'max_buffer_size')) !== null)
    {
      $this->maxBufferSize = $maxBufferSize;
    }

    if (($listenAddress = $config->getValue('main', 'listening_address')) !== null)
    {
      $this->listenAddress = $listenAddress;
    }

    if (($port = $config->getValue('main', 'port')) !== null)
    {
      $this->port = $port;
    }

    if (($maxConnectingQueue = $config->getValue('main', 'connection_queue_length')) !== null)
    {
      $this->maxConnectingQueue = $maxConnectingQueue;
    }

    // Create the socket.
    if (!$this->masterSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))
    {
      throw new \Exception('Failed to create the network socket');
    }

    if (!socket_set_option($this->masterSocket, SOL_SOCKET, SO_REUSEADDR, 1))
    {
      throw new \Exception('Failed to set options on the network socket');
    }

    if (!socket_bind($this->masterSocket, $this->listenAddress, $this->port))
    {
      throw new \Exception('Failed to bind the network socket');
    }

    if (!socket_listen($this->masterSocket, $this->maxConnectingQueue))
    {
      throw new \Exception('Failed to set the network socket to a listening state');
    }

    while(self::$running)
    {
      $sockets = array('m' => $this->masterSocket);
      $connMgr = $factoryClass::create('ConnectionManager');

      $connections = $connMgr->getConnections();

      foreach ($connections as $connection)
      {
        $sockets[$connection->getId()] = $connection->getSocket();
      }

      $write = null;
      $except = null;

      $status = socket_select($sockets, $write, $except, 1);
      if ($status === false)
      {
        throw new \Exception('socket_select() failed: ' . socket_strerror(socket_last_error()));
      }

      foreach ($sockets as $sockId => $socket)
      {
        if ($sockId == 'm')
        {
          $clientHandle = socket_accept($socket);
          if ($clientHandle === false)
          {
            throw new \Exception('socket_accept() failed: '  . socket_strerror(socket_last_error()));
          }
          $connId = $connMgr->newConnection($clientHandle);
          
        }
      }

      echo 'running!';
      self::stop();
    }
    // If we reach this line, it means that we intentionally stopped running.  Reset in case we want to start again.
    self::$running = true;
  }

  public static function stop()
  {
    echo 'stopping!';
    self::$running = false;
  }
}