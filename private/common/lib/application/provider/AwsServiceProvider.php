<?php
/**
 * AwsServiceProvider.php
 *
 * @copyright   Copyright (c) 2013 Yuji Iwai.
 * @package
 * @subpackage
 * @version     $Id$
 */

namespace SMXD\Application\Provider;

use \Phalcon\Cli\Console as ConsoleApp;
use Phalcon\Mvc\Application;
use Aws\Common\Aws;
use Aws\Common\Client\UserAgentListener;
use Guzzle\Common\Event;
use Guzzle\Service\Client;
use Aws\Credentials\CredentialProvider;

class AwsServiceProvider
{

    const DEFAULT_REGION = 'us-east-1';

    private $options;

    /**
     * @param array $options
     */
    function __construct($options = null)
    {
        if (!$options)
            throw new Application\Exception('No configuration given');
        /*
    if (!isset($options['key']))
        throw new Application\Exception('Not key configuration given');
    if (!isset($options['secret']))
        throw new Application\Exception('Not key configuration given');
        */
        if (!isset($options['region']))
            $options['region'] = self::DEFAULT_REGION;

        $this->options = $options;
    }

    /**
     * @param Application $app
     */
    public function boot(ConsoleApp $app)
    {
        $options = $this->options;
        $appConfig = $app->getDI()->getShared('appConfig');
        $app->getDI()->setShared('aws', function () use ($options, $appConfig ) {
            if( $appConfig->application->environment == 'LOCAL' && isset( $appConfig->aws->credentials ) && $appConfig->aws->credentials  != '' && is_file( $appConfig->aws->credentials  )){
                $options['credentials'] = CredentialProvider::ini('default', $appConfig->aws->credentials );
            }
            $aws = new \Aws\Sdk($options);
            $aws->getEventDispatcher()->addListener('service_builder.create_client', function (Event $event) {
                $clientConfig = $event['client']->getConfig();
                $commandParams = $clientConfig->get(Client::COMMAND_PARAMS) ?: array();
                $clientConfig->set(Client::COMMAND_PARAMS, array_merge_recursive($commandParams, array(
                    UserAgentListener::OPTION => 'Phalcon/' . Application::VERSION
                )));
            });
            return $aws;
        });
    }

    /**
     * @param ConsoleApp $app
     */
    public function console(ConsoleApp $app)
    {
        $options = $this->options;
        $appConfig = $app->getDI()->getShared('appConfig');
        $app->getDI()->setShared('aws', function () use ($options, $appConfig ) {
            if( $appConfig->application->environment == 'LOCAL' && isset( $appConfig->aws->credentials ) && $appConfig->aws->credentials  != '' && is_file( $appConfig->aws->credentials  )){
                $options['credentials'] = CredentialProvider::ini('default', $appConfig->aws->credentials );
            }
            $aws = new \Aws\Sdk($options);
            $aws->getEventDispatcher()->addListener('service_builder.create_client', function (Event $event) {
                $clientConfig = $event['client']->getConfig();
                $commandParams = $clientConfig->get(Client::COMMAND_PARAMS) ?: array();
                $clientConfig->set(Client::COMMAND_PARAMS, array_merge_recursive($commandParams, array(
                    UserAgentListener::OPTION => 'Phalcon/' . Application::VERSION
                )));
            });
            return $aws;
        });
    }
}