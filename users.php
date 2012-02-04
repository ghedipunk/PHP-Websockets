<?php

class Users {

	public $socket;
	public $id;
	public $headers = array();

	function __construct($id,$socket) {
		$this->id = $id;
		$this->socket = $socket;
	}
}