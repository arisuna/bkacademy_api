<?php

namespace SMXD\Media;

use \Phalcon\Mvc\Router\Group;

/**
 * This class defines routes for the SMXD\Media module
 * which will be prefixed with '/media'
 */
class ModuleRoutes extends Group
{
    /**
     * Initialize the router group for the Media module
     */
    public function initialize()
    {
        /**
         * In the URI this module is prefixed by '/media'
         */
        $this->setPrefix('/media');

        /**
         * Configure the instance
         */
        $this->setPaths([
            'module' => 'media',
            'namespace' => 'SMXD\Media\Controllers\API\\',
            'controller' => 'index',
            'action' => 'index'
        ]);

        /**
         * Default route: 'media-root'
         */
        $this->addGet('', [])
            ->setName('media-root');

        /**
         * Controller route: 'media-controller'
         */
        $this->addGet('/:controller', ['controller' => 1])
            ->setName('media-controller');

        /**
         * Action route: 'media-action'
         */
        $this->addGet('/:controller/:action/:params', [
            'controller' => 1,
            'action' => 2,
            'params' => 3
        ])
            ->setName('media-action');

        $this->addOptions('/:controller/:action/:params', [
            'controller' => 1,
            'action' => 2,
            'params' => 3
        ])->setName('media-action-options');
        /**
         * Action route: 'media-action'
         */
        $this->addPut('/:controller/:action/:params', [
            'controller' => 1,
            'action' => 2,
            'params' => 3
        ])->setName('media-action-put');

        /**
         * Action route: 'media-action'
         */
        $this->addPost('/:controller/:action/:params', [
            'controller' => 1,
            'action' => 2,
            'params' => 3
        ])
            ->setName('media-action-post');
        /**
         * Add all SMXD\Media specific routes here
         */

        $this->addGet('/file/load/{uuid:([a-zA-Z0-9\_\-]+)}/{type:([a-zA-Z0-9\_\-]+)}/{token}/name/{name}', [
            'controller' => "file",
            'action' => "load",
            'uuid' => 1,
            'type' => 2,
            'token' => 3,
            'name' => 4
        ])->setName('media-file-load');

        $this->addGet('/file/thumbnail/{uuid:([a-zA-Z0-9\_\-]+)}/{type:([a-zA-Z0-9\_\-]+)}/{token}/name/{name}', [
            'controller' => "file",
            'action' => "thumbnail",
            'uuid' => 1,
            'type' => 2,
            'token' => 3,
            'name' => 4
        ])->setName('media-file-thumb');

        $this->addGet('/file/download/{uuid:([a-zA-Z0-9\_\-]+)}/{type:([a-zA-Z0-9\_\-]+)}/{token}/name/{name}', [
            'controller' => "file",
            'action' => "download",
            'uuid' => 1,
            'type' => 2,
            'token' => 3,
            'name' => 4
        ])->setName('media-file-download');

        $this->addGet('/file/full/{uuid:([a-zA-Z0-9\_\-]+)}/{type:([a-zA-Z0-9\_\-]+)}/{token}/name/{name}', [
            'controller' => "file",
            'action' => "full",
            'uuid' => 1,
            'type' => 2,
            'token' => 3,
            'name' => 4
        ])->setName('media-file-full');

        $this->addGet('/file/medium/{uuid:([a-zA-Z0-9\_\-]+)}/{type:([a-zA-Z0-9\_\-]+)}/{token}/name/{name}', [
            'controller' => "file",
            'action' => "load",
            'uuid' => 1,
            'type' => 2,
            'token' => 3,
            'name' => 4
        ])->setName('media-file-medium');

        $this->addGet('/file/mini/{uuid:([a-zA-Z0-9\_\-]+)}/{type:([a-zA-Z0-9\_\-]+)}/{token}/name/{name}', [
            'controller' => "file",
            'action' => "load",
            'uuid' => 1,
            'type' => 2,
            'token' => 3,
            'name' => 4
        ])->setName('media-file-mini');

        /**** item controller ***/

        $this->addGet('/item/getThumbContent/{uuid:([a-zA-Z0-9\_\-]+)}/check/{token}/name/{name}', [
            'controller' => "item",
            'action' => "getThumbContent",
            'uuid' => 1,
            'token' => 2,
            'name' => 3
        ])->setName('media-item-get-thumb-content');

        $this->addGet('/item/downloadContent/{uuid:([a-zA-Z0-9\_\-]+)}/check/{token}/name/{name}', [
            'controller' => "item",
            'action' => "downloadContent",
            'uuid' => 1,
            'token' => 2,
            'name' => 3
        ])->setName('media-item-download-content');

        $this->addGet('/item/viewContent/{uuid:([a-zA-Z0-9\_\-]+)}/check/{token}/name/{name}', [
            'controller' => "item",
            'action' => "viewContent",
            'uuid' => 1,
            'token' => 2,
            'name' => 3
        ])->setName('media-item-view-content');
    }
}
