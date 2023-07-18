<?php

namespace Reloday\Gms;

use \Phalcon\Mvc\Router\Group;

/**
 * This class defines routes for the Reloday\Gms module
 * which will be prefixed with '/gms'
 */
class ModuleRoutes extends Group
{
	/**
	 * Initialize the router group for the Gms module
	 */
	public function initialize()
	{
		/**
		 * In the URI this module is prefixed by '/gms'
		 */
		$this->setPrefix('/gms');

		/**
		 * Configure the instance
		 */
		$this->setPaths([
			'module' => 'gms',
			'namespace' => 'Reloday\Gms\Controllers\API\\',
			'controller' => 'index',
			'action' => 'index'
		]);

		/**
		 * Default route: 'gms-root'
		 */
		$this->addGet('', [])
			->setName('gms-root');

		/**
		 * Controller route: 'gms-controller'
		 */
		$this->addGet('/:controller', ['controller' => 1])
			->setName('gms-controller');

		/**
		 * Action route: 'gms-action'
		 */
		$this->add('/:controller/:action/:params', [
				'controller' => 1,
				'action' => 2,
				'params' => 3
			])
			->setName('gms-action');

		/**
		 * Action route: 'gms-action'
		 */
		$this->add('/:controller/:action/([a-zA-Z0-9-]+)/([a-zA-Z0-9-]+)', [
			'controller' => 1,
			'action' => 2,
			'uuid' => 3,
			'service_uuid' => 4
		])->setName('gms-action-more');

		/**
		 * Add all Reloday\Gms specific routes here
		 */
		$this->addGet('/login', [
			'controller' => 'index',
			'action' => 'login'
		])->setName('gms-controller');


        /**
         * Add all Reloday\Gms specific routes here
         */
        $this->addGet('/needform', [
            'controller' => 'index',
            'action' => 'needform'
        ])->setName('gms-controller');

		/**
		 * Add all Reloday\Gms specific routes here
		 */
		$this->addGet('/translate/login_key/:params', [
			'controller' => 'index',
			'action' => 'translateLoginKey',
			'params' => 1
		])->setName('gms-controller-translate');


		$this->addGet('/translate/forgot_password/:params', [
			'controller' => 'index',
			'action' => 'forgotPasswordKey',
			'params' => 1
		])->setName('gms-controller-translate');

		$this->addGet('/check', [
			'controller' => 'index',
			'action' => 'check'
		])->setName('gms-controller-check');


        $this->addDelete('/uploader/removeAttachment/object/:namespace/media/:namespace', [
            'controller' => 'uploader',
            'action' => 'removeAttachment',
            'object_uuid' => 1,
            'media_uuid' => 2
        ])->setName('gms-controller-uploader-remove');

    }
}
