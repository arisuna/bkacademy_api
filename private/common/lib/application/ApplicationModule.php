<?php

namespace SMXD\Application;
use \Phalcon\Di\Injectable;

use \Phalcon\Mvc\ModuleDefinitionInterface,
    \SMXD\Application\RoutedModule;

/**
 * Abstract application module base class
 */
abstract class ApplicationModule
    extends Injectable
    implements ModuleDefinitionInterface, RoutedModule
{

}
