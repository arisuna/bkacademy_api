<?php

namespace SMXD\Application;

use \Phalcon\Di\DiInterface;

/**
 * Abstract application module base class
 */
interface RoutedModule
{
	/**
     * Load the module specific routes and mount them to the router
     * before the whole module gets loaded and add routing annotated
     * controllers
     *
     * @param \Phalcon\Di\DiInterface $di
     */
    static function initRoutes(DiInterface $di);
}
