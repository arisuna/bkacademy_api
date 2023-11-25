<?php

namespace SMXD\Api;

use Phalcon\Events\Event;
use Phalcon\Http\Request;
use \Phalcon\Loader,
    \Phalcon\DI,
    \Phalcon\Mvc\View,
    \Phalcon\Mvc\Dispatcher,
    \Phalcon\Config,
    \Phalcon\DiInterface,
    \Phalcon\Mvc\Url as UrlResolver,
    \Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
    \SMXD\Application\ApplicationModule;

use Aws\AwsClient;
use SMXD\Application\Provider\AwsServiceProvider;

/**
 * Application module definition for multi module application
 * Defining the Api module
 */
class Module extends ApplicationModule
{
    /**
     * Mount the module specific routes before the module is loaded.
     * Add ModuleRoutes Group and annotated controllers for parsing their routing information.
     *
     * @param \Phalcon\DiInterface $di
     */
    public static function initRoutes(DiInterface $di)
    {
        $loader = new Loader();
        $loader->registerNamespaces([
            'SMXD\Api' => __DIR__,
            'SMXD\Api\Controllers' => __DIR__ . '/controllers/',
            'SMXD\Api\Controllers\API' => __DIR__ . '/controllers/api/'
        ], true)
            ->register();

        /**
         * Add ModuleRoutes Group and annotated controllers for parsing their routing information.
         * Be aware that the parsing will only be triggered if the request URI matches the third
         * parameter of addModuleResource.
         */
        $router = $di->getRouter();
        $router->mount(new ModuleRoutes());

        /**
         * Read names of annotated controllers from the module config and add them to the router
         */
        $moduleConfig = include __DIR__ . '/config/config.php';
        if (isset($moduleConfig['controllers']['annotationRouted'])) {
            foreach ($moduleConfig['controllers']['annotationRouted'] as $ctrl) {
                $router->addModuleResource('api', $ctrl, '/api');
            }
        }
    }

    /**
     * Registers the module auto-loader
     */
    public function registerAutoloaders(DiInterface $di = NULL)
    {
        $loader = new Loader();
        $loader->registerNamespaces([
            'SMXD\Api' => __DIR__,
            'SMXD\Api\Controllers' => __DIR__ . '/controllers/',
            'SMXD\Api\Controllers\API' => __DIR__ . '/controllers/api/',
            'SMXD\Api\Models' => __DIR__ . '/models/',
            'SMXD\Api\Library' => __DIR__ . '/lib/',
        ], true)
            ->register();
    }

    /**
     * Registers the module-only services
     *
     * @param \Phalcon\DiInterface $di
     */
    public function registerServices(DiInterface $di = NULL)
    {
        /**
         * Read application wide and module only configurations
         */
        $appConfig = $di->get('config');
        $moduleConfig = include __DIR__ . '/config/config.php';

        $di->set('moduleConfig', $moduleConfig);
        $di->set('appConfig', $appConfig);
        /**
         * Setting up the view component
         */
        $di->set('view', function () use ($moduleConfig) {
            $view = new View();
            $view->setViewsDir(__DIR__ . '/../../../public/src/app/modules/api/views/')
                ->setLayoutsDir('../../../layouts/')
                ->setLayout("api");

            $view->registerEngines(
                array('.volt' => function ($view, $di) use ($moduleConfig) {
                    $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);
                    $volt->setOptions(
                        array(
                            'compiledPath' => __DIR__ . '/../../../cache/',
                            'compiledSeparator' => '_',
                            'compileAlways' => true,
                        )
                    );
                    return $volt;
                })
            );
            return $view;
        });


        /**
         * The URL component is used to generate all kind of urls in the application
         */
        $di->set('url', function () use ($appConfig) {
            $url = new UrlResolver();
            $url->setBaseUri($appConfig->application->baseUri);
            return $url;
        });

        //Set the models cache service
        $di->set('cache', function () use ($moduleConfig, $appConfig) {
            $frontCache = new \Phalcon\Cache\Frontend\Data(array(
                "lifetime" => $appConfig->cache->lifetime,
            ));
            //Memcached connection settings
            $cache = new \Phalcon\Cache\Backend\Redis($frontCache, array(
                "prefix" => $appConfig->cache->prefix,
                "host" => $appConfig->redis->host,
                "port" => $appConfig->redis->port,
                "persistent" => false,
                "index" => 1 // select db 1
            ));
            return $cache;
        });

        /**
         * Module specific dispatcher
         */
        $di->set('dispatcher', function () use ($di, $moduleConfig) {

            $eventsManager = $di->getShared('eventsManager');
            //$logger = $di->getShared('logger');
            //var_dump($logger);
            $eventsManager->attach(
                "dispatch:beforeException",
                function ($event, $dispatcher, $exception) use ($moduleConfig) {
                    if (getenv('ENVIR') != 'DEV') {
                        switch ($exception->getCode()) {
                            case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
                            case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
                                \Sentry\captureException($exception);
                                $dispatcher->forward(
                                    array(
                                        'controller' => 'index',
                                        'action' => 'error',
                                    )
                                );
                                return false;
                                break;
                            default:
                                \Sentry\captureException($exception);
                                $dispatcher->forward(
                                    array(
                                        'controller' => 'index',
                                        'action' => 'error',
                                    )
                                );
                                return false;
                                break;
                        }
                    }
                }
            );


            $eventsManager->attach(
                "dispatch",
                function (Event $event, $dispatcher) {
                    // ...
                }
            );
            $dispatcher = new Dispatcher();
            $dispatcher->setEventsManager($eventsManager);
            $dispatcher->setDefaultNamespace('SMXD\Api\\');
            return $dispatcher;
        });

        /**
         * Module specific database connection
         */
        $di->set('db', function () use ($appConfig, $moduleConfig) {
            return new DbAdapter([
                'host' => $moduleConfig->database->host,
                'username' => $moduleConfig->database->username,
                'password' => $moduleConfig->database->password,
                'dbname' => $moduleConfig->database->dbname,
                'port' => $moduleConfig->database->port,
                'charset' => $moduleConfig->database->charset,
                'persistent' => false,
            ]);
        });

        $di->set('dbRead', function () use ($appConfig, $moduleConfig) {
            return new DbAdapter([
                'host' => $appConfig->databaseRead->host,
                'username' => $appConfig->databaseRead->username,
                'password' => $appConfig->databaseRead->password,
                'dbname' => $appConfig->databaseRead->dbname,
                'port' => $appConfig->databaseRead->port,
                'charset' => $appConfig->databaseRead->charset,
                'persistent' => false,
            ]);
        });

        $di->setShared('aws', function () use ($appConfig) {
            if ($appConfig->application->environment == 'LOCAL') {
                $options = [
                    'version' => 'latest',
                    'region' => $appConfig->aws->region
                ];
                if ($appConfig->application->environment == 'LOCAL' && isset($appConfig->aws->credentials) && $appConfig->aws->credentials != '' && is_file($appConfig->aws->credentials)) {
                    $options['credentials'] = \Aws\Credentials\CredentialProvider::ini('default', $appConfig->aws->credentials);
                }
                return new \Aws\Sdk($options);
            } else {
                return new \Aws\Sdk([
                    'version' => 'latest',
                    'region' => $appConfig->aws->region
                ]);
            }
        });

        $di->setShared("request", function () {
            return new Request();
        });


        //Set the models cache service
        $di->set('modelsCache', function () use ($moduleConfig) {
            $frontCache = new \Phalcon\Cache\Frontend\Data(array(
                "lifetime" => 86400
            ));
            //Memcached connection settings
            $cache = new \Phalcon\Cache\Backend\Redis($frontCache, array(
                "prefix" => $moduleConfig->application->cachePrefix . "_MODELS_",
                "host" => getenv('REDIS_HOST'),
                "port" => getenv('REDIS_PORT'),
                "persistent" => false,
                "index" => 1 // select db 1
            ));
            return $cache;
        });

        $di->setShared('awsCognitoService', function () use ($appConfig) {
            if ($appConfig->application->environment == 'LOCAL') {
                $options = [
                    'version' => 'latest',
                    'region' => $appConfig->aws->region
                ];
                if ($appConfig->application->environment == 'LOCAL' && isset($appConfig->aws->credentials) && $appConfig->aws->credentials != '' && is_file($appConfig->aws->credentials)) {
                    $options['credentials'] = \Aws\Credentials\CredentialProvider::ini('default', $appConfig->aws->credentials);
                }
                return new \Aws\Sdk($options);
            } else {
                return new \Aws\Sdk([
                    'version' => 'latest',
                    'region' => $appConfig->aws->region
                ]);
            }
        });
    }
}
