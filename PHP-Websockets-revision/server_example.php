<?php 

function __autoload($class) {
  $class=str_replace('_',DIRECTORY_SEPARATOR,$class);
  require_once(__DIR__.'/' . $class . '.php');
}

class phpws {
  //property
  private $eventloop;
  private $apps;

  function __construct($addr,$port,$eventloop = 'socket')
  {
    switch ($eventloop) {
      case 'stream':
      case 'socket':
      case 'libev':
        $eventloop='eventloop_'.$eventloop;
        $this->eventloop=new $eventloop($addr,$port);
        break;
      default : 
        die("$eventloop is not a valid eventloop. Supported eventloop : (stream,socket,libev)");
    }
  }

  function run() {
    //$this->checkintegrity();
    $this->eventloop->run($this->apps);
  }

  private function checkintegrity() {
    //in strict mode make sure we have at least one apps
    //in strict mode off make sure we have a default app
  }

  function addapp($name,$app) {
    $this->apps[$name]= $app;
  }
}

//TODO add broadcast opcode
class msg_data {
	CONST SEND  = 1;
	CONST CLOSE = 2;
  CONST BINARY = 4;
	public $opcode = SELF::SEND; // send by default or close current user
  public $users; //list of users
  public $message; //message to be publish

	function __construct($opcode,$message,$users) {
		$this->users = $users;
		$this->message = $message;
		$this->opcode = $opcode;
	}	
}

//TODO : -add a class who store a list of client connect to the ressource and change $users by this list to broadcast
// -move this class to /ressource
class echobot {
  function onmessage (&$user, $message) {
    //
    //return data to main class is the most logical way of doing this.
    if ($message === 'close')
      return new msg_data(msg_data::CLOSE,$message,$user);
    else
      return new msg_data(msg_data::SEND,$message,$user);
   }
   function onbinary (&$user, $message) {
    //return data to main class is the most logical way of doing this.
    if ($message === 'close')
      return new msg_data(msg_data::CLOSE,$message,$user);
    else
      return new msg_data(msg_data::BINARY,$message,$user);
  }
  function onopen (&$user) {
    // Do nothing: This is just an echo server, there's no need to track the user.
    // However, if we did care about the users, we would probably have a cookie to
    // parse at this step, would be looking them up in permanent storage, etc.
    // Also you can use this for sending a welcome message to the newly client.
  }
  
  function onclose ($user) {
    // Do nothing: This is where cleanup would go, in case the user had any sort of
    // open files or other objects associated with them.  This runs after the socket 
    // has been closed, so there is no need to clean up the socket itself here.
  }
}

$test = new phpws("0.0.0.0","8080",'stream');
$test->addapp("/echobot",new echobot());
$test->run();


