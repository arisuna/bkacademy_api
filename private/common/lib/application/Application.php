<?php

namespace SMXD\Application;

use Phalcon\Application\AbstractApplication as PhalconApplication;

use \Phalcon\Mvc\Url as UrlResolver,
    \Phalcon\Di\DiInterface,
    \Phalcon\Mvc\View,
    \Phalcon\Autoload\Loader,
    \Phalcon\Http\ResponseInterface,
    \Phalcon\Events\Manager as EventsManager,
    \SMXD\Application\Router\ApplicationRouter,
    \Phalcon\Http\Request;

/**
 * Application class for multi module applications
 * including HMVC internal requests.
 */
class Application extends \Phalcon\Mvc\Application
{
    /**
     * Application Constructor
     *
     * @param \Phalcon\Di\DiInterface $di
     */
    public function __construct(DiInterface $di)
    {
        /**
         * Sets the parent DI and register the app itself as a service,
         * necessary for redirecting HMVC requests
         */
        parent::setDI($di);
        $di->set('app', $this);

        /**
         * Register application wide accessible services
         */
        $this->_registerServices();

        /**
         * Register the installed/configured modules
         */
        $this->registerModules(require __DIR__ . '/../../../config/modules.php');
    }

    /**
     * Register the services here to make them general or register in the
     * ModuleDefinition to make them module-specific
     */
    protected function _registerServices()
    {
        /**
         * The application wide configuration
         */
        $config = include __DIR__ . '/../../../config/config.php';
        $this->di->set('config', function () {
            return include  __DIR__ . '/../../../config/config.php';
        });

        /**
         * Setup an events manager with priorities enabled
         */
        $eventsManager = new EventsManager();
        $eventsManager->enablePriorities(true);
        $this->setEventsManager($eventsManager);

        /**
         * Register namespaces for application classes
         */
        $loader = new Loader();
        $loader->setNamespaces([
            'SMXD\Application' => __DIR__,
            'SMXD\Application\Controllers' => __DIR__ . '/controllers/',
            'SMXD\Application\Models' => __DIR__ . '/models/',
            'SMXD\Application\Router' => __DIR__ . '/router/',
            'Phalcon\Utils' => __DIR__ . '/../../../../app/library/utils/',
            'SMXD\Application\Middleware' => __DIR__ . '/middleware/',
            'SMXD\Application\Lib' => __DIR__ . '/lib/',
            'SMXD\Application\Provider' => __DIR__ . '/provider/',
            'SMXD\Application\Validation' => __DIR__ . '/validation/',
            'SMXD\Application\Validator' => __DIR__ . '/validator/',
            'SMXD\Application\Behavior' => __DIR__ . '/behavior/',
            'SMXD\Application\Plugin' => __DIR__ . '/plugin/',
            'SMXD\Application\Aws' => __DIR__ . '/aws/',
            'SMXD\Application\Aws\AwsCognito' => __DIR__ . '/aws/cognito',
            'SMXD\Application\Aws\AwsCognito\Exception' => __DIR__ . '/aws/cognito/exception',
            'SMXD\Application\Resultset' => __DIR__ . '/resultset/',
            'SMXD\Application\Traits' => __DIR__ . '/traits/',
            'SMXD\Application\DynamoDb\ORM' => __DIR__ . '/dynamodb/orm',
            'SMXD\Application\ElasticSearch\Models' => __DIR__ . '/elasticsearch/models',
            'SMXD\Application\CloudModels' => __DIR__ . '/cloud-models',
        ], true)
            ->register();


        $this->di->setShared("request", function () {
            return new Request();
        });

        /**
         * Registering the application wide router with the standard routes set
         */
        $this->di->set('router', new ApplicationRouter());

        /**
         * Specify the use of metadata adapter
         */
        $this->di->set('modelsMetadata', '\Phalcon\Mvc\Model\Metadata\\' . $config->application->models->metadata->adapter);

        /**
         * Specify the annotations cache adapter
         */
        $this->di->set('annotations', '\Phalcon\Annotations\Adapter\\' . $config->application->annotations->adapter);
    }

    /**
     * Register the given modules in the parent and prepare to load
     * the module routes by triggering the init routes method
     */
    public function registerModules(array $modules,bool $merge = false) : PhalconApplication
    {
        parent::registerModules($modules, $merge);

        $loader = new Loader();
        $modules = $this->getModules();

        /**
         * Iterate the application modules and register the routes
         * by calling the initRoutes method of the Module class.
         * We need to auto load the class
         */
        foreach ($modules as $module) {
            $className = $module['className'];

            if (!class_exists($className, false)) {
                $loader->setClasses([$className => $module['path']], true)->register()->autoLoad($className);
            }

            /** @var \SMXD\Application\ApplicationModule $className */
            $className::initRoutes($this->di);
        }
        return $this;
    }

    /**
     * Handles the request and echoes its content to the output stream.
     */
    public function main()
    {
        $baseUri = str_replace('/public/index.php', '', $_SERVER['PHP_SELF']);
        $uri = str_replace($baseUri, '', $_SERVER['REQUEST_URI']);
        echo $this->handle($uri)->getContent();
    }

    /**
     * Does a HMVC request inside the application
     *
     * Inside a controller we might do
     * <code>
     * $this->app->request([ 'controller' => 'do', 'action' => 'something' ], 'param');
     * </code>
     *
     * @param array $location Array with the route information: 'namespace', 'module', 'controller', 'action', 'params'
     * @return mixed
     */
    public function request(array $location)
    {
        /** @var \Phalcon\Mvc\Dispatcher $dispatcher */
        $dispatcher = clone $this->di->get('dispatcher');

        if (isset($location['module'])) {
            $dispatcher->setModuleName($location['module']);
        }

        if (isset($location['namespace'])) {
            $dispatcher->setNamespaceName($location['namespace']);
        }

        if (!isset($location['controller'])) {
            $location['controller'] = 'index';
        }

        if (!isset($location['action'])) {
            $location['action'] = 'index';
        }

        if (!isset($location['params'])) {
            $location['params'] = [];
        }

        $dispatcher->setControllerName($location['controller']);
        $dispatcher->setActionName($location['action']);
        $dispatcher->setParams((array)$location['params']);
        $dispatcher->dispatch();

        $response = $dispatcher->getReturnedValue();

        if ($response instanceof ResponseInterface) {
            return $response->getContent();
        }

        return $response;
    }
}
