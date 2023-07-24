<?php

namespace SMXD\Application\Lib;

class RequestHeaderHelper
{
    const TOKEN = 'token';
    const TOKEN_KEY = 'token-key';
    const EMPLOYEE_TOKEN_KEY = 'ee-token-key';
    const BACKEND_TOKEN = 'backend-token';
    const BACKEND_TOKEN_KEY = 'backend-token-key';
    const LANGUAGE_KEY = 'language-key';
    const TIMEZONE = 'timezone';
    const TIMEZONE_OFFSET = 'timezone-offset';
    const AUTHORIZATION = 'authorization';
    const X_REQUEST_WITH = 'x-requested-with';
    const CONTENT_TYPE = 'content-type';
    const ORIGIN = 'origin';
    const REFRESH_TOKEN = 'refresh-token';
    const ACCEPT_RANGES = 'accept-ranges';
    const RANGE = 'range';
    const RANGES = 'ranges';
    const PASSWORD = 'password';
    const REFERER = 'referer';

    static function __getAll()
    {
        return implode(",", [
            self::TOKEN,
            self::TOKEN_KEY,
            self::EMPLOYEE_TOKEN_KEY,
            self::BACKEND_TOKEN,
            self::BACKEND_TOKEN_KEY,
            self::LANGUAGE_KEY,
            self::TIMEZONE,
            self::TIMEZONE_OFFSET,
            self::AUTHORIZATION,
            self::X_REQUEST_WITH,
            self::CONTENT_TYPE,
            self::REFRESH_TOKEN,
            self::ACCEPT_RANGES,
            self::RANGE,
            self::RANGES,
            self::PASSWORD,
            self::REFERER
        ]);
    }
}