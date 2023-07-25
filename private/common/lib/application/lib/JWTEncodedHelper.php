<?php

namespace SMXD\Application\Lib;

use \Firebase\JWT\JWT;
use Phalcon\Http\Client\Provider\Exception;
use Jwt\Exception\ExpiredException;


class JWTEncodedHelper
{

    static $errorType = '';
    static $errorMessage = '';
    const ERROR_EXPIRED = 'ERROR_EXPIRED';
    const ERROR_UNEXPECTED = 'ERROR_UNEXPECTED';
    const ERROR_SIGNATURE_INVALID = 'ERROR_SIGNATURE_INVALID';
    const ERROR_INVALID = 'ERROR_INVALID';
    const ERROR_UNDEFINED = 'ERROR_UNDEFINED';
    const ERROR_DOMAIN = 'ERROR_DOMAIN';

    /**
     *
     */
    public static function __init()
    {
        JWTExt::$leeway = 0;
    }

    /**
     * //set data and use JWT
     * @param $data
     */
    public static function encode($data = array(), $expiredHour = 0)
    {
        if ($expiredHour == 0) $expiredHour = 365 * 24;
        try {
            $data['iat'] = time();
            $data['exp'] = time() + $expiredHour * 60 * 60;

            //expired time not thing
            $data_encoded = JWT::encode($data, getenv('JWT_KEY'), getenv('JWT_ALG'));
            return $data_encoded;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param $jwt_encoded
     * @return string
     */
    public static function decode($jwt_encoded)
    {
        if ($jwt_encoded == '' || $jwt_encoded == null) return null;
        try {
            $data_decoded = JWT::decode($jwt_encoded, getenv('JWT_KEY'), array(getenv('JWT_ALG')));
            return (array)$data_decoded;
        } catch (\UnexpectedValueException $e) {
            return null;
        } catch (\Firebase\JWT\ExpiredException $e) {
            return null;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return null;
        } catch (\Phalcon\Exception $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param $jwt_encoded
     * @return null|object
     */
    public static function decodeWithKey($jwt_encoded, $jwt_key, $jwt_algorithm, $is_array = false)
    {
        if ($jwt_encoded == '' || $jwt_encoded == null) return null;
        try {
            $data_decoded = JWTExt::decode($jwt_encoded, $jwt_key, array($jwt_algorithm));
            if ($is_array == false) {
                return $data_decoded;
            } else {
                return (array)$data_decoded;
            }
        } catch (\Firebase\JWT\ExpiredException $e) {
            self::$errorType = self::ERROR_EXPIRED;
            self::$errorMessage = $e->getMessage();
            return null;
        } catch (\UnexpectedValueException $e) {
            self::$errorType = self::ERROR_UNEXPECTED;
            self::$errorMessage = $e->getMessage();
            return null;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            self::$errorType = self::ERROR_SIGNATURE_INVALID;
            self::$errorMessage = $e->getMessage();
            return null;
        } catch (\DomainException $e) {
            self::$errorType = self::ERROR_DOMAIN;
            self::$errorMessage = $e->getMessage();
            return null;
        } catch (\ErrorException $e) {
            self::$errorType = self::ERROR_UNDEFINED;
            self::$errorMessage = $e->getMessage();
            return null;
        } catch (\Exception $e) {
            self::$errorType = self::ERROR_UNDEFINED;
            self::$errorMessage = $e->getMessage();
            return null;
        }
    }

    /**
     * @param $json
     */
    public static function decodeAwsCognitoSignature($json)
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid Aws Argument.');
        }

        return JWK::parseKeySet($json);
    }

    /**
     * Creates a JWKSet object using the given values.
     *
     * @param array $data
     *
     * @return JWKSet
     */
    public static function createFromKeyData(array $data)
    {
        if (!array_key_exists('keys', $data) || !is_array($data['keys'])) {
            throw new \InvalidArgumentException('Invalid data.');
        }

        $keys = [];
        foreach ($data['keys'] as $keyArray) {
            $keyArray = (array)$keyArray;
            if (isset($keyArray['kid'])) {
                $keys[$keyArray['kid']] = $keyArray;
                continue;
            }
            $keys[] = $keyArray;
        }
        return $keys;
    }

    /**
     * @param $jwtToken
     * @return null|object
     */
    public static function __getPayload($jwtToken)
    {
        $tks = explode('.', $jwtToken);
        if (count($tks) != 3) {
            return null;
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;
        if (null === ($header = JWT::jsonDecode(JWT::urlsafeB64Decode($headb64)))) {
            return null;
        }
        if (null === $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($bodyb64))) {
            return null;
        }
        return $payload ? (array)$payload : null;
    }

    /**
     *
     */
    public static function __isExpired()
    {
        return self::$errorType == self::ERROR_EXPIRED;
    }

    /**
     * @param $token
     */
    public static function __isTokenExpired($token)
    {

    }
}

?>