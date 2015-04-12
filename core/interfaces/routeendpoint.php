<?php
namespace Phpws\Interfaces

interface RouteEndpoint
  public function process($user, $message);
  public function connected($user);
  public function closed($user);
}
