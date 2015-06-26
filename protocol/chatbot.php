<?php 

trait protocol_chatbot {
  function chatbot(&$user,$action) {
    $this->stdout("< ".$action."\r\n",true);
    switch($action){
      case "hello" : $this->send($user,"hello human");                       break;
      case "hi"    : $this->send($user,"zup human");                         break;
      case "name"  : $this->send($user,"my name is Multivac, silly I know"); break;
      case "age"   : $this->send($user,"I am older than time itself");       break;
      case "date"  : $this->send($user,"today is ".date("Y.m.d"));           break;
      case "time"  : $this->send($user,"server time is ".date("H:i:s"));     break;
      case "thanks": $this->send($user,"you're welcome");                    break;
      case "bye"   : $this->send($user,"bye");                               break;
      case "help"  : $this->send($user,"I respond to this command : hello,hi,name,age,date,time,thanks,bye,help"); break;
      default      : $this->send($user,"Repeat: ".$action." type: 'help'");
    }
  }
}
