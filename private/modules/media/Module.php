<?php

namespace SMXD\Media;

use Aws\AwsClient;
use Phalcon\Events\Event;
use Phalcon\Exception;
use \Phalcon\Loader,
    \Phalcon\DI,
    \Phalcon\Mvc\View,
    \Phalcon\Mvc\Dispatcher as Dispatcher,
    \Phalcon\Config,
    \Phalcon\Di\DiInterface,
    \Phalcon\Mvc\Url as UrlResolver,
    \Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
    \SMXD\Application\ApplicationModule,
    \Phalcon\Http\Request;
use Phalcon\Mvc\Model\Manager;
use SMXD\Media\Models\ModuleModel;
use SMXD\Application\Lib\SMXDDynamoORM;
use SMXD\Application\Provider\AwsServiceProvider;

/**
 * Application module definition for multi module application
 * Defining the Media module
 */
class Module extends ApplicationModule
{
    /**
     * Mount the module specific routes before the module is loaded.
     * Add ModuleRoutes Group and annotated controllers for parsing their routing information.
     *
     * @param \Phalcon\Di\DiInterface $di
     */
    public static function initRoutes(DiInterface $di)
    {
        $loader = new Loader();
        $loader->registerNamespaces([
            'SMXD\Media' => __DIR__,
            'SMXD\Media\Controllers' => __DIR__ . '/controllers/',
            'SMXD\Media\Controllers\API' => __DIR__ . '/controllers/api/'
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
                $router->addModuleResource('media', $ctrl, '/media');
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
            'SMXD\Media' => __DIR__,
            'SMXD\Media\Controllers' => __DIR__ . '/controllers/',
            'SMXD\Media\Controllers\API' => __DIR__ . '/controllers/api/',
            'SMXD\Media\Models' => __DIR__ . '/models/',
            'SMXD\Media\Library' => __DIR__ . '/lib/',
        ], true)
            ->register();
    }

    /**
     * Registers the module-only services
     *
     * @param \Phalcon\Di\DiInterface $di
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
            $view->setViewsDir(__DIR__ . '/../../../public/src/app/modules/media/views/')
                ->setLayoutsDir('../../../layouts/')
                ->setPartialsDir('../../../partials/')
                ->setLayout("classic");

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
        $di->set('url', function () use ($moduleConfig) {
            $url = new UrlResolver();
            $url->setBaseUri($moduleConfig->application->baseUri);
            return $url;
        });

        /**
         * Module specific dispatcher
         */
        $di->set('dispatcher', function () use ($di, $moduleConfig, $appConfig) {
            $dispatcher = new Dispatcher();
            $dispatcher->setEventsManager($di->getShared('eventsManager'));
            $dispatcher->setDefaultNamespace('SMXD\Media\\');
            $eventsManager = $di->getShared('eventsManager');
            $eventsManager->attach("application:boot", new AwsServiceProvider(array(
                'region' => $appConfig->aws->region
            )));
            $dispatcher->setEventsManager($eventsManager);
            return $dispatcher;
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
                'port' => $appConfig->database->port,
                'charset' => $appConfig->database->charset,
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
            ]);
        });

        //Set the models cache service
        $di->set('modelsCache', function () use ($appConfig, $moduleConfig) {
            $frontCache = new \Phalcon\Cache\Frontend\Data(array(
                "lifetime" => 86400
            ));
            //Memcached connection settings
            $cache = new \Phalcon\Cache\Backend\Redis($frontCache, array(
                "prefix" => $appConfig->cache->prefix . "_MODELS_",
                "host" => getenv('REDIS_HOST'),
                "port" => getenv('REDIS_PORT'),
                "persistent" => false,
                "index" => 1 // select db 1
            ));
            return $cache;
        });

        $di->setShared('filter', function () {
            $filter = new \Phalcon\Filter();
            $filter->add('email', function ($value) {
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
            });
            return $filter;
        });


        //Set the models cache service
        $di->setShared('cacheRedisMedia', function () use ($moduleConfig) {
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

        //Set the models cache service
        $di->set('cache', function () use ($moduleConfig) {
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

        /** get aws */
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
