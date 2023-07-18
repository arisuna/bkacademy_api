<?php

namespace Reloday\Application\Plugin;

use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Micro\MiddlewareInterface;
use Phalcon\Http\Request;

use Phalcon\Mvc\User\Plugin;
/**
 * CORSMiddleware
 *
 * CORS checking
 */
class CorsPlugin extends Plugin
{
    /**
     * Before anything happens
     * @param Event $event
     * @param Application $application
     * @returns bool
     */
    public function beforeHandleRoute(Event $event, \Phalcon\Mvc\Dispatcher $dispatcher)
    {
        return $this->checkCors($event, $dispatcher);
    }

    /**
     * Before anything happens
     * @param Event $event
     * @param Application $application
     * @returns bool
     */
    public function beforeExecuteRoute(Event $event, \Phalcon\Mvc\Dispatcher $dispatcher)
    {
        return $this->checkCors($event, $dispatcher);
    }

    /**
     * @param Event $event
     * @param Dispatcher $dispatcher
     * @return bool
     */
    public function checkCors(Event $event, Dispatcher $dispatcher)
    {

        $request = new Request();
        if ($request->getHeader('ORIGIN')) {
            $origin = $request->getHeader('ORIGIN');
        } else {
            $origin = '*';
        }

        $this->response->setHeader("Access-Control-Allow-Origin", "*");
        $this->response->setHeader("Access-Control-Allow-Methods", 'GET, PUT, POST, DELETE, OPTIONS');
        $this->response->setHeader("Access-Control-Allow-Headers", strtolower('Access-Control-Allow-Origin, Accept, Token-Key, Origin, X-Requested-With, Content-Range, Content-Disposition, Content-Type, Authorization, If-Modified-Since,Cache-Control, Range, DNT,User-Agent'));
        $this->response->setHeader("Access-Control-Allow-Credentials", true);
        $this->response->setHeader("Access-Control-Max-Age", 1728000);

        //$this->response->setHeader("Content-Type", "application/json; text/html; charset=utf-8");
        $this->response->sendHeaders();
        //var_dump( $this->response->getHeaders() );

        return true;
    }

    /**
     * Calls the middleware
     *
     * @param Micro $application
     *
     * @returns bool
     */
    public function call(\Phalcon\Mvc\Micro $application)
    {
        return true;
    }
}