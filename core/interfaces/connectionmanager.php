<?php
namespace Phpws\Interfaces;

interface ConnectionManager
{
  public function addConnection (\Phpws\Interfaces\Connection $connection);
  public function getConnection($connectionId);
  public function getConnections();
  public function removeConnection($connectionId);
  public function newConnection($handle);
}
