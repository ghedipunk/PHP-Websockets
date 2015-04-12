<?php
namespace Phpws;

class GlobalConfig implements \Phpws\Interfaces\GlobalConfig
{

  private $config = null;
  // Protected so that we can enforce the singleton pattern
  protected function __construct()
  {
    if (file_exists('config/config.ini'))
    {
      if (($conf = parse_ini_file('config/config.ini', true)) !== false)
      {
        $this->config = $conf;
      }
    }
  }

  private function __clone()
  {
  }

  private function __wakeup()
  {
  }
  /*
   * Alright, so you're upset about the word 'singleton', right?
   *
   * Well, here's the justification. The configuration is really and truly both
   * global and intrinsic (natrually tightly coupled) to the application.
   *
   * If you're still not convinced, then what is your issue with singletons?  Is
   * you're objection based simply on singletons being bad?
   *
   * Do you know why singletons are bad?  It's because they introduce global state
   * and prevent dependency injection.   The rest of the program becomes tightly
   * coupled to that specific implementation of that class.  These are taught
   * as "bad" things, so singletons are naturally bad, right?
   *
   * Here's the thing, though.  Global variables and tight coupling are only bad
   * when used naively.  Overwriting values in a global variable without understanding
   * the entire system, or being unable to use alternative versions of similar 
   * functionality are obvious problems introduced when working with global state and
   * tight coupling.  If other developers assume that a global variable doesn't get
   * changed often, then that's an issue when you change it.  If you really need to
   * of an object that is tightly coupled, you'll be redoing other people's code, which
   * change an implementation is a huge problem if you can't merge back into the trunk,
   * and a potential source of bugs whether you can merge or not.
   *
   * Obviously it's best to avoid global state and tight coupling... but what if something
   * really and truly is global (the configuration) and directly tied to the fundamental
   * structure of your application, so much so that it appears right in the first
   * file loaded and called explicitly?
   *
   * If you really want to swap it out, the configuration object is initialized too
   * early to perform any swapping done through the factory. In fact, in this system, it 
   * can't be loaded after the factory is loaded, because the factory itself depends
   * on a configuration to read from, to know what objects to swap in during object creation.
   *
   * So if you really want to swap out the global configuration class, write a new front
   * controller... That's what they're there for.
   */
  public static function getSingleton()
  {
    static $instance = null;

    if ($instance === null)
    {
      $instance = new \Phpws\GlobalConfig();
    }

    return $instance;
  }

  public function getValue($section, $name)
  {
    if (isset($this->config[$section][$name]))
    {
      return $this->config[$section][$name];
    }
    return null;
  }


}