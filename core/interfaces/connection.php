<?php
namespace Phpws\Interfaces

interface Connection
{
  public function setHandle($socketHandle);
  public function getHandle();
  public function setId($id);
  public function getId();
}