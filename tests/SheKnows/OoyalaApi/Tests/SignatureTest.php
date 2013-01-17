<?php

namespace SheKnows\OoyalaApi\Tests;

use SheKnows\OoyalaApi\Signature;
use Guzzle\Http\Message\Request;

class SignatureTest extends BaseTestCase
{
    /**
     * Testing the raw signature value before it's hashed.
     *
     * Signatures should match Ooyala's {@link http://support.ooyala.com/developers/documentation/tasks/api_signing_requests.html Signing Request Algorithm}.
     */
    public function testValidRawSignature()
    {
        $method = 'GET';
        $path   = '/my/path';
        $key    = '123';
        $secret = '456';
        // Purposely out of order to test sorting from the Response object query params.
        $queryParams = array(
            'fake'     => 'fake,param',
            'another'  => '123',
            'key'      => $key
        );

        $request = new Request($method, $path);
        foreach ($queryParams as $key => $val) {
            $request->getQuery()->set($key, $val);
            unset($key, $val);
        }

        $signature = new Signature($secret, $request);

        // Build expected signature by hand to simulate what \SheKnows\OoyalaApi\Signature::getRawSignature() does.
        // @see \SheKnows\OoyalaApi\Signature for more details.
        $expected = $secret . $method . $path;
        ksort($queryParams); // Sort manually, kind of makes for an ugly test :/
        foreach ($queryParams as $key => $val) {
            $expected .= "{$key}={$val}";
        }

        $this->assertEquals(
            $expected,
            $signature->getRawSignature(),
            'Raw signature should match {secret} + {HttpMethod} + {UriPath} + {ordered_query_params}'
        );

        $this->assertEquals(43, strlen($signature), 'Hashed signature should be 43 characters in length.');
    }

    /**
     * Guzzle should take care of encoding signatures. This test is just a safety net to ensure that happens.
     */
    public function testUrlEncodedSignature()
    {
        $expected = '1234 ] 5678';
        $request = new Request('GET', "/fake?signature={$expected}");

        $url = explode('signature=', $request->getUrl());
        $signature = end($url);

        $this->assertEquals(
            rawurlencode($expected),
            $signature,
            'Request should be encoding the signature before sending (required by Ooyala).'
        );
    }
}