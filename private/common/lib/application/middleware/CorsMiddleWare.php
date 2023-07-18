<?php

namespace Reloday\Application\Middleware;

use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Micro\MiddlewareInterface;
use Phalcon\Http\Request;

/**
 * CORSMiddleware
 *
 * CORS checking
 */
class CorsMiddleWare implements MiddlewareInterface
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

    public function checkCors(Event $event, Dispatcher $dispatcher)
    {
        $request = new Request();
        if ($request->getHeader('ORIGIN')) {
            $origin = $request->getHeader('ORIGIN');
        } else {
            $origin = '*';
        }

        $response = new \Phalcon\Http\Response();
        $response->setHeader('Access-Control-Allow-Origin', $origin);
        $response->setHeader(
            'Access-Control-Allow-Methods',
            'GET,PUT,POST,DELETE,OPTIONS'
        );
        $response->setHeader(
            'Access-Control-Allow-Headers',
            'Token-Key. ' .
            'Origin, X-Requested-With, Content-Range, ' .
            'Content-Disposition, Content-Type, Authorization'
        );
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->sendHeaders();

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