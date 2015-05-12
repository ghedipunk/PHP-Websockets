<?php
namespace \Phpws

class ConnectionManager implements \Phpws\Interfaces\ConnectionManager
{
  private $connections;

  public function __construct()
  {
    $this->connections = array();
  }

  public function addConnection (\Phpws\Interfaces\Connection $connection)
  {
    if (($id = $connection->getId()) === null)
    {
      $connection->setId((max(array_keys($this->connections)) + 1);
        $id = $connection->getId();
    }
    $this->connections[$id] = $connection;
    
    return $id;
  }

  public function getConnection($connectionId)
  {
    if (isset($this->connections[$connectionId]))
    {
      return $this->connections[$connectionId];
    }
    return null;
  }

  public function getConnections()
  {
    return $this->connections;
  }

  public function removeConnection($connectionId)
  {
    unset ($this->connections[$connectionId]);
  }

  public function newConnection($socketHandle)
  {
    $config = \Phpws\GlobalConfig::getSingleton();

    if (($factoryClass = $config->getValue('factory overrides', 'FactoryClass')) === null)
    {
      $factoryClass = '\Phpws\Factory';
    }

    $conn = $factoryClass::create('Connection');
    
  }
}
