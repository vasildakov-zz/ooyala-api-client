<?php

namespace SheKnows\OoyalaApi;

use \Guzzle\Http\Message\Request;

/**
 * Class representing an Ooyala Request signature algorithm.
 *
 * @link http://support.ooyala.com/developers/documentation/tasks/api_signing_requests.html
 * @link http://support.ooyala.com/developers/documentation/api/signature_php.html
 *
 */
final class Signature
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var String
     */
    private $apiSecret;

    public function __construct($apiSecret, Request $request)
    {
        $this->apiSecret = $apiSecret;
        $this->request = $request;
    }

    public function getRawSignature()
    {
        $request = $this->request;
        $parameters = $request->getQuery()->toArray();
        $keys = $this->sortKeys($parameters);

        $to_sign = $this->apiSecret . $request->getMethod() . $request->getPath();
        foreach ($keys as $key) {
            $to_sign .= $key . "=" . $parameters[$key];
        }

        // Get the entity body (POST, PUT, PATCH, DELETE)
        if ($request->getMethod() !== Request::GET) {
            $to_sign .= $request->getBody();
        }

        return $to_sign;
    }

    public function getHashedSignature()
    {
        $hash = hash("sha256", $this->getRawSignature(), true);
        $base = base64_encode($hash);
        $base = substr($base, 0, 43);

        return $base;
    }

    public function __toString()
    {
        return $this->getHashedSignature();
    }

    /**
     * Convenience function to sort request parameter keys in order.
     * The order of request parameters is important to generating a valid request signature.
     *
     * @param array $array
     *
     * @return array
     */
    private function sortKeys(array $array)
    {
        $keys = array();
        $ind = 0;

        foreach ($array as $key => $val) {
            $keys[$ind++] = $key;
        }

        sort($keys);

        return $keys;
    }
}