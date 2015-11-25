<?php 
namespace Gpws\Eventloop;

//need Ev library http://php.net/manual/fr/book.ev.php
trait Libev {
  final public function run() {
    $this->mem = memory_get_usage();

    switch(Ev::backend()) {
      case Ev::BACKEND_SELECT:
        $backend = "Select()";
        break;
      case Ev::BACKEND_POLL:
        $backend = "Poll";
        break;
      case Ev::BACKEND_EPOLL:
        $backend = "Epoll"; // use by linux before and after kernels 2.6.9
        break;
      case Ev::BACKEND_KQUEUE:
        $backend = "Kqueue"; // use by BSD systems
        break;
      case Ev::BACKEND_PORT:
        $backend = "Event Port"; // use by Solaris systems
        break;     
    }

    $this->stdout("RUNNING with libev method BACKEND : $backend");
    $w_listen = new EvIo($this->master, Ev::READ, function ($w) {
      $client = socket_accept($this->master);
      if ( $client === FALSE) {
        $this->stdout("Failed: socket_accept()");
        return;
      }
      else if ( $client > 0) {
        if (!socket_set_nonblock($client)) {
          $this->stdout("Failed: socket_set_nonblock() id #" . $client);
        }

        $user = $this->connect($client);
        $this->stdout("Client #$this->nbclient connected. " . $client);
        $w_read = new EvIo($client, Ev::READ , function ($wr) use ($client,&$user) {
          $start=$this->getrps();
            $this->stdout("read callback",true);
            $this->cb_read($user);
          $this->getrps($start,'<< read');
        });
        $this->readWatchers[$user->id]=&$w_read;
        $w_read->start();
      }		  
    });
    Ev::run();
  }

  protected function addWriteWatchers(&$user,$open) {
    if ($open) {
      $w_write = new EvIo($user->socket, Ev::WRITE , function () use (&$user) {
        $start=$this->getrps();
          if ($user->writeNeeded) {
            $this->stdout("write callback",true);
            $this->ws_write($user);
          }
        $this->getrps($start,'>> write');
      });
      $this->writeWatchers[$user->id]=&$w_write;
      $w_write->start();	  
    }
    else {
      $this->writeWatchers[$user->id]->stop();
      $this->writewatchers[$user->id]=null;
      unset($this->writeWatchers[$user->id]);
    }
  }
}

