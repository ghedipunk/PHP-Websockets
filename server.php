#!/usr/bin/env php
<?php
namespace \Phpws;

require_once(__DIR__ . '/core/bootstrap.php');

class WebsocketServer extends \Phpws\Core\Server\Server {
    use \Phpws\Core\Eventloop\Socket;
}

$ws = new WebsocketServer('0.0.0.0', 9000);
$ws->run();
