<?php

namespace Reloday\Gms;

use Aws\AwsClient;
use Phalcon\Events\Event;
use Phalcon\Exception;
use \Phalcon\Loader,
    \Phalcon\DI,
    \Phalcon\Mvc\View,
    \Phalcon\Mvc\Dispatcher as Dispatcher,
    \Phalcon\Config,
    \Phalcon\DiInterface,
    \Phalcon\Mvc\Url as UrlResolver,
    \Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
    \Reloday\Application\ApplicationModule,
    \Phalcon\Http\Request;
use Phalcon\Mvc\Model\Manager;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Provider\AwsServiceProvider;


/**
 * Application module definition for multi module application
 * Defining the Gms module
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
            'Reloday\Gms' => __DIR__,
            'Reloday\Gms\Controllers' => __DIR__ . '/controllers/',
            'Reloday\Gms\Controllers\API' => __DIR__ . '/controllers/api/'
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
                $router->addModuleResource('gms', $ctrl, '/gms');
            }
        }

        $router->notFound([
            "module" => 'gms',
            "controller" => "index",
            "action" => "route404"
        ]);

    }

    /**
     * Registers the module auto-loader
     */
    public function registerAutoloaders(DiInterface $di = NULL)
    {
        $loader = new Loader();
        $loader->registerNamespaces([
            'Reloday\Gms' => __DIR__,
            'Reloday\Gms\Controllers' => __DIR__ . '/controllers/',
            'Reloday\Gms\Controllers\API' => __DIR__ . '/controllers/api/',
            'Reloday\Gms\Models' => __DIR__ . '/models/',
            'Reloday\Gms\Library' => __DIR__ . '/lib/',
            'Reloday\Gms\Help' => __DIR__ . '/help/',
        ], true)->register();
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
        $di->set('view',
            function () use ($moduleConfig) {
                $view = new View();
                $view->setViewsDir(__DIR__ . '/../../../public/src/app/modules/gms/views/')
                    ->setLayoutsDir('../../../layouts/')
                    ->setPartialsDir('../../../partials/')
                    ->setLayout("gms-angular-layout");

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

                        $volt->getCompiler()->addFunction('ng', function ($input) {
                            return '"{{".' . $input . '."}}"';
                        });
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

        /**
         * Module specific dispatcher
         */
        $di->set('dispatcher', function () use ($di, $moduleConfig, $appConfig) {
            $dispatcher = new Dispatcher();

            $eventsManager = $di->getShared('eventsManager');

            $eventsManager->attach(
                "dispatch:beforeException",
                function ($event, $dispatcher, $exception) use ($moduleConfig) {
                    Helpers::__trackError($exception);
                    switch ($exception->getCode()) {
                        case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
                        case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
                            $dispatcher->forward([
                                'controller' => 'error',
                                'action' => 'index',
                                'params' => [
                                    'exception' => $exception
                                ]
                            ]);
                            return false;
                            break;
                        default:
                            $dispatcher->forward([
                                'controller' => 'error',
                                'action' => 'php',
                                'params' => [
                                    'exception' => $exception
                                ]
                            ]);
                            return false;
                            break;
                    }
                }
            );


            $eventsManager->attach("application:boot", new AwsServiceProvider(array(
                'region' => $appConfig->aws->region
            )));


            $dispatcher->setEventsManager($eventsManager);
            $dispatcher->setDefaultNamespace('Reloday\Gms\\');
            return $dispatcher;
        });

        /**
         * Attach event BEFORE_SAVE for all model to write log
         */

        $di->setShared(
            'modelsManager',
            function () use ($di) {
                $dispatcher = new Dispatcher();
                $eventsManager = $di->getShared('eventsManager');
                global $action;
                $action = 'UPDATE';

                $eventsManager->attach('model:beforeSave',
                    function (Event $event, $model) {
                        // List model will always be executed before call identified model
                        $class_list = [
                            "Reloday\\Gms\\Models\\UserLoginToken"
                        ];

                        if (!in_array(get_class($model), $class_list)) {
                            if (empty($model->getSnapshotData())) {
                                global $action;
                                $action = 'CREATE';
                            }
                        }

                    });

                $eventsManager->attach(
                    "model:afterSave",
                    function (Event $event, $model) use ($di) {
                        // List model will always be executed before call identified model
                        $class_list = [
                            "Reloday\\Gms\\Models\\UserLoginToken"
                        ];
                    });

                $eventsManager->attach(
                    "model:afterDelete",

                    function (Event $event, $model) use ($di) {

                    }
                );
                // Setting a default EventsManager
                $modelsManager = new Manager();
                $modelsManager->setEventsManager($eventsManager);
                return $modelsManager;
            }
        );

        $di->setShared('redis', function () {
            $redis = new \Redis();
            $redis->pconnect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
            return $redis;
        });


        $di->set('login',
            function () {
                return [];
            }
        );


        $di->set('closeMessageBox', function () {
            return '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
        });

        $di->set('moduleConfig', function () use ($moduleConfig) {
            return $moduleConfig;
        });

        //Set the models cache service
        $di->set('cache', function () use ($moduleConfig) {
            //Cache data for one day by default
            $frontCache = new \Phalcon\Cache\Frontend\Data(array(
                "lifetime" => CacheHelper::__TIME_24H,
            ));

            //Memcached connection settings
            $cache = new \Phalcon\Cache\Backend\Redis($frontCache, array(
                "prefix" => getenv('CACHE_PREFIX'),
                "host" => getenv('REDIS_HOST'),
                "port" => getenv('REDIS_PORT'),
                "persistent" => false,
                "index" => 1 // select db 1
            ));
            return $cache;
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

        //Set the models cache service
        $di->set('cacheRedisMedia', function () use ($moduleConfig) {
            //Cache data for one day by default
            $frontCache = new \Phalcon\Cache\Frontend\Data(array(
                "lifetime" => getenv('CACHE_TIME')
            ));

            //Memcached connection settings
            $cache = new \Phalcon\Cache\Backend\Redis($frontCache, array(
                "prefix" => getenv('CACHE_PREFIX'),
                "host" => getenv('REDIS_HOST'),
                "port" => getenv('REDIS_PORT'),
                "persistent" => false,
                "index" => 1 // select db 1
            ));
            return $cache;
        });

        /**
         * Module specific database connection
         */

        $di->set('db', function () use ($appConfig, $moduleConfig) {
            return new DbAdapter([
                'host' => $appConfig->database->host,
                'username' => $appConfig->database->username,
                'password' => $appConfig->database->password,
                'dbname' => $appConfig->database->dbname,
                'charset' => $appConfig->database->charset,
                'port' => $appConfig->database->port,
                'persistent' => false,
            ]);
        });


        $di->set('dbRead', function () use ($appConfig, $moduleConfig) {
            return new DbAdapter([
                'host' => $appConfig->databaseRead->host,
                'username' => $appConfig->databaseRead->username,
                'password' => $appConfig->databaseRead->password,
                'dbname' => $appConfig->databaseRead->dbname,
                'charset' => $appConfig->databaseRead->charset,
                'port' => $appConfig->databaseRead->port,
                'persistent' => false,
            ]);
        });

        /**
         * Module Communication specific database connection
         */
        $di->set('dbCommunicationRead', function () use ($appConfig, $moduleConfig) {
            return new DbAdapter([
                'host' => $appConfig->databaseCommunicationRead->host,
                'username' => $appConfig->databaseCommunicationRead->username,
                'password' => $appConfig->databaseCommunicationRead->password,
                'dbname' => $appConfig->databaseCommunicationRead->dbname,
                'port' => $appConfig->databaseCommunicationRead->port,
                'charset' => $appConfig->databaseCommunicationRead->charset,
                'persistent' => false,
            ]);
        });

        $di->set('dbCommunicationWrite', function () use ($appConfig, $moduleConfig) {
            return new DbAdapter([
                'host' => $appConfig->databaseCommunicationWrite->host,
                'username' => $appConfig->databaseCommunicationWrite->username,
                'password' => $appConfig->databaseCommunicationWrite->password,
                'dbname' => $appConfig->databaseCommunicationWrite->dbname,
                'port' => $appConfig->databaseCommunicationWrite->port,
                'charset' => $appConfig->databaseCommunicationWrite->charset,
                'persistent' => false,
            ]);
        });

        /**
         * Module specific database connection
         */
        $di->set('dbWebhook', function () use ($appConfig, $moduleConfig) {
            return new DbAdapter([
                'host' => $appConfig->databaseWebhook->host,
                'username' => $appConfig->databaseWebhook->username,
                'password' => $appConfig->databaseWebhook->password,
                'dbname' => $appConfig->databaseWebhook->dbname,
                'port' => $appConfig->databaseWebhook->port,
                'charset' => $appConfig->databaseWebhook->charset,
                'persistent' => false,
            ]);
        });

        $di->setShared('aws', function () use ($appConfig) {
            if ($appConfig->application->isLocal == true) {
                $options = [
                    'version' => 'latest',
                    'region' => $appConfig->aws->region
                ];
                if ($appConfig->application->isLocal == true && isset($appConfig->aws->credentials) && $appConfig->aws->credentials != '' && is_file($appConfig->aws->credentials)) {
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
        $di->setShared('awsCognitoService', function () use ($appConfig) {
            if ($appConfig->application->environment == 'LOCAL') {
                $options = [
                    'version' => 'latest',
                    'region' => $appConfig->aws->awsCognitoRegion
                ];
                if ($appConfig->application->environment == 'LOCAL' && isset($appConfig->aws->credentials) && $appConfig->aws->credentials != '' && is_file($appConfig->aws->credentials)) {
                    $options['credentials'] = \Aws\Credentials\CredentialProvider::ini('default', $appConfig->aws->credentials);
                }
                return new \Aws\Sdk($options);
            } else {
                return new \Aws\Sdk([
                    'version' => 'latest',
                    'region' => $appConfig->aws->awsCognitoRegion
                ]);
            }
        });

        $di->setShared("request", function () {
            return new Request();
        });

    }
}
