<?php

spl_autoload_register('phpws_autoload');

function phpws_autoload($class) {

	$classParts = explode('\\', strtolower($class));

	$root = array_unshift($classParts);
	// If it's not in our namespace, don't try to autoload it.
	if ($root !== 'phpws') {
		return;
	}

	// If it's an interface, it doesn't match normal naming conventions.
	if ($classParts[0] === 'interfaces')
	{
		if (file_exists(__DIR__ . '/interfaces/' . $classParts[1] . '.php')) {
			require_once(__DIR__ . '/interfaces/' . $classParts[1] . '.php');
		}
		return; // Whether we found the file or not, if the namespace was \Phpws\Interfaces\xxx, then we quit here.
	}

	$path = implode('/', $classParts);
	if (file_exists(dirname(__DIR__) . $path . '.php')) {
		require_once(dirname(__DIR__) . '/' . $path . '.php');
	}
}