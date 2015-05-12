<?php
namespace Phpws

class Connection implements \Phpws\Interfaces\Connection
{
  private $socketHandle;
  private $id = null;

  public function setHandle($socketHandle)
  {
    $this->socketHandle = $socketHandle;
  }

  public function getHandle()
  {
    return $this->socketHandle;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function getId()
  {
    return $this->id;
  }
}