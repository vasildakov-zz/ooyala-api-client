<?php

namespace SheKnows\OoyalaApi\Tests;

use SheKnows\OoyalaApi\OoyalaClient;

class OoyalaClientTest extends BaseTestCase
{
    /**
     * @var \SheKnows\OoyalaApi\OoyalaClient
     */
    private $client;

    public function testValidClient()
    {
        $this->assertInstanceOf('\SheKnows\OoyalaApi\OoyalaClient', $this->getClient());
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

    public function testValidSignatureResponse()
    {
        $client = $this->getClient();
        $command = $client->getCommand('GetAssets');

        /** @var $response \Guzzle\Http\Message\Response */
        $client->execute($command);
        $response = $command->getResponse();
        // If the signature was correct, response should return a 200 status.
        $this->assertEquals('200', $response->getStatusCode());
    }

    /**
     * @group internet
     * @expectedException Guzzle\Http\Exception\BadResponseException
     */
    public function testRequiredParameterMissingResponse()
    {
        $client = clone $this->getClient();
        $command = $client->getCommand('GetAssets');

        $beforeSend = function (\Guzzle\Common\Event $event) {
            /** @var $request \Guzzle\Http\Message\Request */
            $request = $event['request'];
            $request->getQuery()
                ->remove('expires')
                ->remove('api_key')
                ->remove('signature')
            ;
        };

        // Very low priority so this runs after the OoyalaClient listener.
        $client->getEventDispatcher()->addListener('request.before_send', $beforeSend, -9999999999);

        try {
            $response = $command->execute();
        } catch (\Guzzle\Http\Exception\BadResponseException $e) {
            $response = $e->getResponse();
            $body = $response->getBody(true);
            $this->assertEquals('{"message":"These parameters are missing: api_key, signature, expires."}', $body);

            $client->getEventDispatcher()->removeListener('request.before_send', $beforeSend);

            throw $e;
        }
    }

    /**
     * @expectedException Guzzle\Http\Exception\BadResponseException
     * @group internet
     */
    public function testInvalidSignatureResponse()
    {
        $client = clone $this->getClient();
        $command = $client->getCommand('GetAssets');

        $beforeSend = function (\Guzzle\Common\Event $event) {
            /** @var $request \Guzzle\Http\Message\Request */
            $request =& $event['request'];
            if ($request->getQuery()->hasKey('signature')) {
                $request->getQuery()->set('signature', 'coocoocachoo!');
            }
        };

        $client->getEventDispatcher()->addListener('request.before_send', $beforeSend, -9999999999);

        try {
            $command->execute();
        } catch (\Guzzle\Http\Exception\BadResponseException $e) {
            $response = $e->getResponse();
            $request = $e->getRequest();
            $body = json_decode($response->getBody(true));
            $this->assertObjectHasAttribute('message', $body);
            $this->assertEquals($body->message, 'Invalid signature.');

            $client->getEventDispatcher()->removeListener('request.before_send', $beforeSend);

            throw $e;
        }
    }
}