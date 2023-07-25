<?php

namespace SMXD\Application\Lib;

class SMXDUrlHelper
{
    const PROTOCOL_HTTPS = 'https';

    const PROTOCOL_HTTP = 'http';

    /**
     * @return string
     */
    public static function __getMainUrl()
    {
        $di = \Phalcon\DI::getDefault();
        return (($di->getShared('appConfig')->domain->ssl == true) ? self::PROTOCOL_HTTPS : self::PROTOCOL_HTTP) . "://" . $di->getShared('appConfig')->domain->prefix . "." . $di->getShared('appConfig')->domain->suffix;
    }

    /**
     * @return string
     */
    public static function __getApiUrl()
    {
        $di = \Phalcon\DI::getDefault();
        return (($di->getShared('appConfig')->domain->ssl == true) ? self::PROTOCOL_HTTPS : self::PROTOCOL_HTTP) . "://" . $di->getShared('appConfig')->domain->api;
    }

    /**
     * @return string
     */
    public static function __getInternalApiUrl()
    {
        $di = \Phalcon\DI::getDefault();
        return (self::PROTOCOL_HTTP) . "://" . $di->getShared('appConfig')->domain->api;
    }

    /**
     *
     */
    public static function __getMainDomain()
    {
        $di = \Phalcon\DI::getDefault();
        return $di->getShared('appConfig')->domain->prefix . "." . $di->getShared('appConfig')->domain->suffix;
    }

    /**
     * @param string $subdomain
     */
    public static function __getSubUrl($subdomain = '')
    {
        if ($subdomain == '') {
            return self::__getMainUrl();
        } elseif (Helpers::__isDomainPrefix($subdomain)) {
            $di = \Phalcon\DI::getDefault();
            return (($di->getShared('appConfig')->domain->ssl == true) ? 'https' : 'http') . "://" . $subdomain . "." . $di->getShared('appConfig')->domain->suffix;
        } else {
            return self::__getMainUrl();
        }
    }

    /**
     * @param string $subdomain
     */
    public static function __getSubDomain($subdomain = '')
    {
        if ($subdomain == '') {
            return self::__getMainDomain();
        } elseif (Helpers::__isDomainPrefix($subdomain)) {
            $di = \Phalcon\DI::getDefault();
            return $subdomain . "." . $di->getShared('appConfig')->domain->suffix;
        } else {
            return self::__getMainDomain();
        }
    }

    /**
     *
     */
    public static function __getAppSuffix()
    {
        $di = \Phalcon\DI::getDefault();
        return $di->getShared('appConfig')->domain->suffix;
    }

    /**
     * @return string
     */
    public static function __getFormatUrl()
    {
        return "#^([a-zA-Z0-9\-_]+)\." . self::__getAppSuffix() . "$#";
    }

    /**
     * get backend url
     * @return string
     */
    public static function __getBackendUrl()
    {
        $di = \Phalcon\DI::getDefault();
        return (($di->getShared('appConfig')->domain->ssl == true) ? self::PROTOCOL_HTTPS : self::PROTOCOL_HTTP) . "://" . $di->getShared('appConfig')->domain->backend;
    }

    /**
     * @param $uuid
     */
    public static function __getDashboardUrl()
    {
        return self::__getMainUrl() . "/app/#/app/dashboard";
    }


    /**
     * @return mixed
     */
    public static function __getCalledUrl()
    {
        $request = new \Phalcon\Http\Request();
        return self::__trimQueryString($request->getHTTPReferer());
        //trim(strtok($request->getHTTPReferer(), "?"), "/");
    }

    /**
     * @return mixed
     */
    public static function __getCalledDomain()
    {
        $request = new \Phalcon\Http\Request();
        return self::__trimQueryString($request->getHTTPReferer());
    }

    /**
     * @param $url
     * @return string
     */
    public static function __trimQueryString($url)
    {
        $parseArray = parse_url($url);

        if(!isset($parseArray['scheme'])){
            $parseArray['scheme'] = self::PROTOCOL_HTTPS;
        }

        if(!isset($parseArray['host'])){
           return '';
        }


        return trim($parseArray['scheme'] . "://" . $parseArray['host'] . "/" . trim($parseArray['path'], "/"), "/");
        //trim(strtok($request->getHTTPReferer(), "?"), "/");
    }
}