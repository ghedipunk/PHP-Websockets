<?php

/**
 * Dependencies container.
 *
 * Note, this should not be used as a service locator within the GPWS library itself.  The GPWS objects have dependencies,
 * and this class will provide defaults for those dependencies, or will use configuration options to allow these default
 * dependencies to be resolved by other suitable replacements (Convention Over Configuration).
 *
 * This is intended to ease testing and provide sane defaults, not straight-jacket a library user into any one way of
 * doing things. Using a dependency container is optional, which means that if omitted, the library users will be expected
 * to resolve the dependencies themselves.
 *
 * As with any dependency injection container, it only makes sense if the code is separated out into a "library" section
 * and an "application" section, where the application handles the high level interactions with the users and the library
 * deals with the nitty gritty details of implementing those interactions.  The application should be free to use the
 * dependency container to create objects, whereas the library must be fed each of its dependencies and must have no
 * idea whether those dependencies were provided by a injector, by application code that hard codes those dependencies,
 * or by a test suite, and similarly should have no idea about the implementation details of their dependencies, or
 * have any way to tell a "live" injected dependency from a test mockup.
 *
 * Best to think of this as a general purpose factory object that actively looks for dependencies in a config file.
 */

namespace Gpws;

use Gpws\Core\GlobalConfig;

class Dependencies {
    public function __construct(GlobalConfig $config) {
        $this->config = $config;
    }

    public function createSharedClass($className) {
        if (empty($this->sharedDependencies[$className])) {
            $cn = $this->getClassInstance($className);
            
        }
        return $this->sharedDependencies[$className];
    }

    /**
     * Used to override
     *
     * @param $interfaceName
     * @param $classInstance
     */
    public function setClassInstance($interfaceName, $classInstance) {

    }

    private function getClassInstance($interfaceName) {
        if (!is_null($val = $this->config->getValue('dependency_mappings', 'EventLoop'))) {
            return $val;
        }

        // Could not find a config value for this interface; use the hard-coded defaults.
        switch($interfaceName) {
            case 'Gpws\Interfaces\Eventloop':
                $instance = new \Gpws\Eventloop\Socket();
                return $instance;
            default:
                throw new \UnexpectedValueException('The interface named could not be resolved to a class definition in the dependencies container object.');
        }
    }

    private $config;

    private $sharedDependencies;
}
