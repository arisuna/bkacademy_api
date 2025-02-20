<?php

namespace SMXD\App;

use \Phalcon\Loader,
    \Phalcon\DI,
    \Phalcon\Mvc\View,
    \Phalcon\Mvc\Dispatcher,
    \Phalcon\Config,
    \Phalcon\Di\DiInterface,
    \Phalcon\Mvc\Url as UrlResolver,
    \Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
    \SMXD\Application\ApplicationModule,
    \Phalcon\Queue\Beanstalk,
    \Phalcon\Queue\Beanstalk\Extended as BeanstalkExtended,
    \Phalcon\Session\Adapter\Files,
    \Phalcon\Http\Request;
use Aws\AwsClient;
use SMXD\Application\Provider\AwsServiceProvider;

/**
 * Application module definition for multi module application
 * Defining the App module
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
            'SMXD\App' => __DIR__,
            'SMXD\App\Controllers' => __DIR__ . '/controllers/',
            'SMXD\App\Controllers\API' => __DIR__ . '/controllers/api/'
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
                $router->addModuleResource('app', $ctrl, '/app');
            }
        }
    }

    /**
     * Registers the module auto-loader
     */
    public function registerAutoloaders(DiInterface $di = null)
    {
        $loader = new Loader();
        $loader->registerNamespaces([
            'SMXD\App' => __DIR__,
            'SMXD\App\Controllers' => __DIR__ . '/controllers/',
            'SMXD\App\Controllers\API' => __DIR__ . '/controllers/api/',
            'SMXD\App\Models' => __DIR__ . '/models/',
            'SMXD\App\Library' => __DIR__ . '/lib/',
        ], true)
            ->register();
    }

    /**
     * Registers the module-only services
     *
     * @param \Phalcon\Di\DiInterface $di
     */
    public function registerServices(DiInterface $di = null)
    {
        /**
         * Read application wide and module only configurations
         */
        $appConfig = $di->get('config');
        $moduleConfig = include __DIR__ . '/config/config.php';

        $di->set('moduleConfig', $moduleConfig);
        $di->set('appConfig', $appConfig);
        $di->set('view', function () use ($moduleConfig) {
            $view = new View();
            $view->setViewsDir(__DIR__ . '/../../../public/src/app/modules/app/views/')
                ->setLayoutsDir('../../../layouts/')
                ->setPartialsDir('../../../partials/')
                ->setLayout("angular-layout");

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
                }
                )
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
        $di->set('modelsCache', function () use ($moduleConfig, $appConfig) {
            $frontCache = new \Phalcon\Storage\SerializerFactory();
            //Memcached connection settings
            $cache = new \Phalcon\Cache\Adapter\Redis($frontCache, array(
                "prefix" => $appConfig->cache->modelPrefix,
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
        $di->set('dispatcher', function () use ($di) {
            $dispatcher = new Dispatcher();
            $dispatcher->setEventsManager($di->getShared('eventsManager'));
            $dispatcher->setDefaultNamespace('SMXD\App\\');
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

        /**
         * Re-declare flashSession
         */
        $di->set('flashSession', function () {
            $flashSession = new \Phalcon\Flash\Session(array(
                'error' => 'alert alert-danger',
                'success' => 'alert alert-success',
                'warning' => 'alert alert-warning'
            ));
            return $flashSession;
        });

        /**
         * Start the session the first time some component request the session service
         */
        $di->set(
            'session',
            function () {
                $session = new Files();
                $session->start();
                return $session;
            }
        );

        $di->set('closeMessageBox', function () {
            return '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
        });

        //Set the models cache service
        $di->set('cache', function () use ($moduleConfig, $appConfig) {
            $frontCache = new \Phalcon\Storage\SerializerFactory();
            //Memcached connection settings
            $cache = new \Phalcon\Cache\Adapter\Redis($frontCache, array(
                "prefix" => $appConfig->cache->prefix,
                "host" => $appConfig->redis->host,
                "port" => $appConfig->redis->port,
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
