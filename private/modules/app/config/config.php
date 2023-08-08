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
			'\SMXD\App\Controllers\API\Setting',
			'\SMXD\App\Controllers\API\Lang',
			'\SMXD\App\Controllers\API\App',
			'\SMXD\App\Controllers\API\User',
			'\SMXD\App\Controllers\API\Auth',
			'\SMXD\App\Controllers\API\Index',
		]
	],
    'cache' => [
        'prefix' => 'RELODAY_APP',
        'time' => getenv('CACHE_TIME')
    ]
]);
