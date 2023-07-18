<?php

/*
  +------------------------------------------------------------------------+
  | Phalcon Framework                                                      |
  +------------------------------------------------------------------------+
  | Copyright (c) 2011-2016 Phalcon Team (https://www.phalconphp.com)      |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file LICENSE.txt.                             |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to license@phalconphp.com so we can send you a copy immediately.       |
  +------------------------------------------------------------------------+
  | Authors: Nikita Vershinin <endeveit@gmail.com>                         |
  +------------------------------------------------------------------------+
*/

use Phalcon\Queue\Beanstalk\Extended as BeanstalkExtended;
use Phalcon\Logger\Adapter as LoggerAdapter;
use Phalcon\Queue\Beanstalk as Base;
use Phalcon\Queue\Beanstalk\Job as Job;

/**
 * \Phalcon\Queue\Beanstalk\Extended
 *
 * Extended class to access the beanstalk queue service.
 * Supports tubes prefixes, pcntl-workers and tubes stats.
 *
 * @package Phalcon\Queue\Beanstalk
 */
class ReloQueue extends BeanstalkExtended
{
    /**
     * @param string $tube
     * @param callable $callback
     */
    public function addWorker($tube, $callback)
    {
        $workerId = uniqid();
        if (!is_string($tube)) {
            throw new \InvalidArgumentException('The tube name must be a string.');
        }

        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('The callback is invalid.');
        }

        $this->workers[$workerId] = ['tube' => $tube, 'callback' => $callback];

    }

    /**
     * @param bool $ignoreErrors
     * @throws Exception
     */
    public function doWork($ignoreErrors = false)
    {

    }
}
