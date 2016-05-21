<?php

namespace Gpws\Interfaces;

interface EventLoop
{
	public function run();
	public function addMasterConnection($masterConnection);
}
