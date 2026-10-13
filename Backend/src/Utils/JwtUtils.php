<?php
namespace App\Utils;

class JwtUtils
{
    private static $algo = 'HS256';

    private static function secret(): string
    {
        return Config::get('jwt_secret');
    }

    public static function generate($payload)
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algo]);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::secret(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function validate($token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3)
            return false;

        [$header, $payload, $signature] = $parts;

        $validSignature = hash_hmac('sha256', $header . "." . $payload, self::secret(), true);
        $base64UrlSignature = self::base64UrlEncode($validSignature);

        // hash_equals: comparación en tiempo constante
        if (hash_equals($base64UrlSignature, $signature)) {
            $data = json_decode(self::base64UrlDecode($payload), true);
            if (!is_array($data)) return false;
            if (isset($data['exp']) && $data['exp'] < time()) return false;
            return $data;
        }

        return false;
    }

    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
