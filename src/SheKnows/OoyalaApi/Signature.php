<?php

namespace SheKnows\OoyalaApi;

use Guzzle\Http\Message\EntityEnclosingRequestInterface;
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

    /**
     * Constructor.
     *
     * @param $apiSecret Ooyala client Api Secret.
     * @param \Guzzle\Http\Message\Request $request \Guzzle\Http\Message\Request object
     */
    public function __construct($apiSecret, Request $request)
    {
        $this->apiSecret = $apiSecret;
        $this->request = $request;
    }

    /**
     * Builds the raw Ooyala signature from the $request object.
     *
     * @return string Returns the raw signature without hashing applied.
     */
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
        if ($request->getMethod() !== Request::GET && $request instanceof EntityEnclosingRequestInterface) {
            $to_sign .= $request->getBody();
        }

        return $to_sign;
    }

    /**
     * Get the hashed version of the raw signature to use in a request to the Ooyala backlot api.
     *
     * @return string Hashed version of the request signature.
     */
    public function getHashedSignature()
    {
        $hash = hash("sha256", $this->getRawSignature(), true);
        $base = base64_encode($hash);
        $base = substr($base, 0, 43);

        return $base;
    }

    /**
     * Hashed request signature when this object is treated like a string.
     *
     * @return string Hashed version of the request signature.
     */
    public function __toString()
    {
        return $this->getHashedSignature();
    }

    /**
     * Convenience function to sort request parameter keys in order.
     * The order of request parameters is important to generating a valid request signature.
     *
     * @param array $array array of key/value pairs that need to be sorted to match Ooyala's hashing algorithm.
     *
     * @return array The array in sorted order.
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