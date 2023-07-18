<?php
use \Firebase\JWT\JWT;

class JWTEngineEncodedHelper
{
    /**
     * //set data and use JWT
     * @param $data
     */
    public static function encode($data = array()){
        $data['iat'] = time();
        $data['exp'] = time() + 365*24*60;
        $data_encoded = JWT::encode($data, getenv('JWT_KEY'));
        return $data_encoded;
    }

    /**
     * @param $jwt_encoded
     * @return string
     */
    public static function decode($jwt_encoded){
        $data_decoded= JWT::decode($jwt_encoded, getenv('JWT_KEY'), array('HS256'));
        return $data_decoded;
    }
}

?>