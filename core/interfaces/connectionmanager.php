<?php
namespace Phpws\Interfaces;

interface ConnectionManager
{
  public function addConnection (\Phpws\Interfaces\Connection $connection);
  public function findConnection($connecionId);
  public function removeConnection($connectionId);
}
