<?php

namespace Gpws\Connections;

use Gpws\Interfaces\Selectable;

class Socket implements Selectable {
	public function select(&$read, &$write, &$except, $tv_sec, $tv_usec = 0) {
		return socket_select($read, $write, $except, $tv_sec, $tv_usec);
	}
}

