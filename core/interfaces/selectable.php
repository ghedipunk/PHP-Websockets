<?php

namespace Gpws\Interfaces;

interface Selectable {
	
	public function select(&$read, &$write, &$except, $tv_sec, $tv_usec = 0);
}
