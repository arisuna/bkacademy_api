<?php

namespace SMXD\App;

use \Phalcon\Mvc\Router\Group;

/**
 * This class defines routes for the SMXD\App module
 * which will be prefixed with '/app'
 */
class ModuleRoutes extends Group
{
	/**
	 * Initialize the router group for the App module
	 */
	public function initialize()
	{
		/**
		 * In the URI this module is prefixed by '/app'
		 */
		$this->setPrefix('/app');

		/**
		 * Configure the instance
		 */
		$this->setPaths([
			'module' => 'app',
			'namespace' => 'SMXD\App\Controllers\API\\',
			'controller' => 'index',
			'action' => 'index'
		]);

		/**
		 * Default route: 'app-root'
		 */
		$this->addGet('', [])
			->setName('app-root');

		/**
		 * Controller route: 'app-controller'
		 */
		$this->addGet('/:controller', ['controller' => 1])
			->setName('app-controller');

		/**
		 * Action route: 'app-action'
		 */
		$this->add('/:controller/:action/:params', [
				'controller' => 1,
				'action' => 2,
				'params' => 3
			])
			->setName('app-action');

		/**
		 * Add all SMXD\App specific routes here
		 */
		$this->add('/lang/i18n/([a-z]{2})', [
				'controller' => "lang",
				'action' 	 => "i18n",
				'lang' 	 	 => 1
			])
			->setName('app-lang-detail');
	}
}
