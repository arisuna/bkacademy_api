<?php

namespace Reloday\Application;

use \Phalcon\Mvc\ModuleDefinitionInterface,
    \Phalcon\Mvc\User\Module as UserModule,
    \Reloday\Application\RoutedModule;

/**
 * Abstract application module base class
 */
abstract class ApplicationModule
    extends UserModule
    implements ModuleDefinitionInterface, RoutedModule
{

}
