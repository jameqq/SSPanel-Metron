<?php

namespace App\Services;

use Firebase\JWT\JWT as JwtClient;
use Firebase\JWT\Key;

class Jwt
{
    private static function getKey()
    {
        return $_ENV['key'];
    }

    public static function encode($input)
    {
        return JwtClient::encode($input, self::getKey(), 'HS256');
    }

    public static function encode_withkey($input, $key)
    {
        return JwtClient::encode($input, $key, 'HS256');
    }

    public static function decodeArray($input)
    {
        return JwtClient::decode($input, new Key(self::getKey(), 'HS256'));
    }
}
