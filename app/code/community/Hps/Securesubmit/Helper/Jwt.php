<?php

/**
 * JWT encoding
 *
 * PHP Version 5.2+
 *
 * @category Authentication
 * @package  HPS
 * @author   Heartland Payment Systems <entapp_devportal@e-hps.com>
 * @license  Custom https://github.com/hps/heartland-php/blob/master/LICENSE.txt
 * @link     https://developer.heartlandpaymentsystems.com
 */

/**
 * JWT encoding
 *
 * PHP Version 5.2+
 *
 * @category Authentication
 * @package  HPS
 * @author   Heartland Payment Systems <entapp_devportal@e-hps.com>
 * @license  Custom https://github.com/hps/heartland-php/blob/master/LICENSE.txt
 * @link     https://developer.heartlandpaymentsystems.com
 */
class Hps_Securesubmit_Helper_Jwt extends Mage_Core_Helper_Abstract
{
    /**
     * Encodes a JWT with a `$key` and a `$payload`
     *
     * @param string $key     key used to sign the JWT
     * @param mixed  $payload payload to be included
     *
     * @return string
     */
    public static function encode($key = '', $payload = array())
    {
        $header = array('typ' => 'JWT', 'alg' => 'HS256');

        $parts = array(
            self::urlsafeBase64Encode(json_encode($header)),
            self::urlsafeBase64Encode(json_encode($payload)),
        );
        $signingData = implode('.', $parts);
        $signature = self::sign($key, $signingData);
        $parts[] = self::urlsafeBase64Encode($signature);

        return implode('.', $parts);
    }

    /**
     * Signs a set of `$signingData` with a given `$key`
     *
     * @param string $key         key used to sign the JWT
     * @param string $signingData data to be signed
     *
     * @return string
     */
    public static function sign($key, $signingData)
    {
        return hash_hmac('sha256', $signingData, $key, true);
    }

    /**
     * Verify a JWT
     *
     * @param string $jwt JWT to be verified
     * @param string $key signing key
     *
     * @return boolean
     */
    public static function verify($jwt, $key)
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;
        $signingData = sprintf('%s.%s', $header, $payload);
        $hash = hash_hmac('sha256', $signingData, $key, true);
        $signature = self::urlsafeBase64Dencode($signature);

        return self::hashEquals($signature, $hash);
    }

    /**
     * Creates a url-safe base64 encoded value
     *
     * @param string $data data to be encoded
     *
     * @return string
     */
    protected static function urlsafeBase64Encode($data)
    {
        return str_replace('=', '', strtr(base64_encode($data), '+/', '-_'));
    }

    /**
     * Decodes a url-safe base64 encoded value
     *
     * @param string $data base64 encoded value
     *
     * @return string
     */
    protected static function urlsafeBase64Dencode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Compares two hashes for equality
     *
     * @param string $signature previous value
     * @param string $hash      calculated value
     *
     * @return boolean
     */
    protected static function hashEquals($signature, $hash)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($signature, $hash);
        }

        if (self::safeStrLength($signature) !== self::safeStrLength($hash)) {
            return false;
        }

        $xor = $signature ^ $hash;
        $result = 0;

        for ($i = self::safeStrLength($xor) - 1; 0 <= $i; $i--) {
            $result |= ord($xor[$i]);
        }

        return (0 === $result);
    }

    /**
     * Gets string length
     *
     * @param string $string value to check
     *
     * @return number
     */
    protected static function safeStrLength($string)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($string);
        }

        return strlen($string);
    }
}
