<?php

return new \Phalcon\Config([
	'database' => [
        'adapter' => getenv('DB_ADAPTER'),
        'host' => getenv('DB_HOST'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'dbname' => getenv('DB_DATABASE'),
        'port' => getenv('DB_PORT'),
		'persistent' 	=> true,
		'charset' 		=> 'utf8'
	],
	'controllers' => [
		'annotationRouted' => [
			'\Reloday\App\Controllers\API\Subscription',
			'\Reloday\App\Controllers\API\Setting',
			'\Reloday\App\Controllers\API\Lang',
			'\Reloday\App\Controllers\API\Login',
			'\Reloday\App\Controllers\API\Password',
			'\Reloday\App\Controllers\API\App',
			'\Reloday\App\Controllers\API\User',
			'\Reloday\App\Controllers\API\Auth',
			'\Reloday\App\Controllers\API\Index',
		]
	],
    'cache' => [
        'prefix' => 'RELODAY_APP',
        'time' => getenv('CACHE_TIME')
    ]
]);
