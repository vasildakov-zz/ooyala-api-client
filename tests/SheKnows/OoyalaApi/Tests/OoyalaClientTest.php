<?php

namespace SheKnows\OoyalaApi\Tests;

use SheKnows\OoyalaApi\OoyalaClient;
use Guzzle\Tests\GuzzleTestCase;

class OoyalaClientTest extends GuzzleTestCase
{
    /**
     * @var \SheKnows\OoyalaApi\OoyalaClient
     */
    private $client;

    public function testValidClient()
    {
        $this->assertInstanceOf('\SheKnows\OoyalaApi\OoyalaClient', $this->client);
    }

    /**
     * @expectedException Guzzle\Common\Exception\InvalidArgumentException
     * @expectedExceptionMessage Config must contain a 'api_key' key
     */
    public function testMissingRequiredParametersThrowsException()
    {
        OoyalaClient::factory();
    }

    /**
     * @expectedException Guzzle\Common\Exception\InvalidArgumentException
     * @expectedExceptionMessage Config must contain a 'api_secret' key
     */
    public function testMissingApiSecretThrowsException()
    {
        OoyalaClient::factory(array('api_key' => '123'));
    }

    public function testValidClientInstanceWhenRequiredParametersPresent()
    {
        $client = OoyalaClient::factory(array(
            'api_key' => '123',
            'api_secret' => '456'
        ));
        $this->assertInstanceOf('SheKnows\OoyalaApi\OoyalaClient', $client);
    }

    /**
     * Attempt at testing the raw signature value before it's hashed.
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

        $client = OoyalaClient::factory(array(
            'api_key'     => $key,
            'api_secret'  => $secret,
        ));

        $request = new \Guzzle\Http\Message\Request($method, $path);
        foreach ($queryParams as $key => $val) {
            $request->getQuery()->set($key, $val);
            unset($key, $val);
        }

        // Build expected signature by hand to simulate what \SheKnows\OoyalaApi\OoyalaClient::getRawSignature() does.
        // @see \SheKnows\OoyalaApi\OoyalaClient::signRequest() for more details.
        $expected = $secret . $method . $path;
        ksort($queryParams); // Sort manually, kind of makes for an ugly test :/
        foreach ($queryParams as $key => $val) {
            $expected .= "{$key}={$val}";
        }

        $this->assertEquals(
            $expected,
            $client->getRawSignature($request),
            'Raw signature should match {secret} + {HttpMethod} + {UriPath} + {ordered_query_params}'
        );

        // Compare generated signatures
        $expected = $client->hashSignature($expected);
        $client->signRequest($request);
        $this->assertEquals($expected, $request->getQuery()->get('signature'));
    }

    protected function setUp()
    {
        $this->client = $this->getServiceBuilder()->get('test.ooyala-client');
    }
}