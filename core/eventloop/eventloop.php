<?php
namespace Phpws;

class EventLoop implements \Phpws\Interfaces\EventLoop
{
  private $maxBufferSize;
  private $masterSocket;
  private $connectionManager;

  public function run()
  {
    // Set defaults
    $this->maxBufferSize = 2048;

    $config = \Phpws\GlobalConfig::getSingleton();

    if (($maxBufferSize = $config->getValue('main', 'max_buffer_size')) !== null)
    {
      $this->maxBufferSize = $maxBufferSize;
    }
  }
}