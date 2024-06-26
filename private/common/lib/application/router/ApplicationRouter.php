<?php

namespace SMXD\Application\Router;

use \Phalcon\Mvc\Router\Annotations as Router;

/**
 * This class acts as the application router and defines global application routes.
 * Module specific routes are defined inside the ModuleRoutes classes. Since this
 * class extends the \Phalcon\Mvc\Router\Annotations class annotations are also allowed
 * for routing. Therefore add the Controllers as resources in the ModuleRoutes by invoking
 * addModuleResource on an instance of this class. Remember to register the controllers to the
 * autoloader.
 */
class ApplicationRouter extends Router
{
    /**
     * Creates a new instance of ApplicationRouter class and defines standard application routes
     * @param boolean $defaultRoutes
     */
    public function __construct($defaultRoutes = false)
    {
        parent::__construct($defaultRoutes);

        $this->removeExtraSlashes(true);

        /**
         * Controller and action always default to 'index'
         */
        $this->setDefaults([
            'controller' => 'index',
            'action' => 'index'
        ]);

        /**
         * Add global matching route for the default module 'Frontend': 'default-route'
         */
        $this->add('/', [
            'module' => 'app',
            'namespace' => 'SMXD\App\Controllers\API\\'
        ])->setName('default-route');

        /*switch ($_SERVER['HTTP_HOST']) {
            case 'expat.reloday.local':
                $this->add('/', [
                    'module' => 'gms',
                    'namespace' => 'SMXD\Frontend\Controllers\API\\'
                ])->setName('default-route');
                break;
            default:
                $this->add('/', [
                    'module' => 'frontend',
                    'namespace' => 'SMXD\Frontend\Controllers\API\\'
                ])->setName('default-route');
                break;
        }*/

        /**
         * Add default not found route
         */
        $this->notFound([
            'controller' => 'index',
            'action' => 'route404'
        ]);
    }
}
