<?php

namespace Gpws\NetworkPrimitives;

use Gpws\Interfaces\Selectable;

class Stream implements Selectable {
	public function select(&$read, &$write, &$except, $tv_sec, $tv_usec = 0) {
		return stream_select($read, $write, $except, $tv_sec, $tv_usec);
	}
}
