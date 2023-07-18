<?php

namespace Reloday\Api;

use \Phalcon\Mvc\Router\Group;

/**
 * This class defines routes for the Reloday\Api module
 * which will be prefixed with '/api'
 */
class ModuleRoutes extends Group
{
	/**
	 * Initialize the router group for the Api module
	 */
	public function initialize()
	{
		/**
		 * In the URI this module is prefixed by '/api'
		 */
		$this->setPrefix('/api');

		/**
		 * Configure the instance
		 */
		$this->setPaths([
			'module' => 'api',
			'namespace' => 'Reloday\Api\Controllers\API\\',
			'controller' => 'index',
			'action' => 'index'
		]);

		/**
		 * Default route: 'api-root'
		 */
		$this->addGet('', [])
			->setName('api-root');

		/**
		 * Controller route: 'api-controller'
		 */
		$this->addGet('/:controller', ['controller' => 1])
			->setName('api-controller');

		/**
		 * Action route: 'api-action'
		 */
		$this->add('/:controller/:action/:params', [
				'controller' => 1,
				'action' => 2,
				'params' => 3
			])
			->setName('api-action');

		/**
		 * Add all Reloday\Api specific routes here
		 */
	}
}
