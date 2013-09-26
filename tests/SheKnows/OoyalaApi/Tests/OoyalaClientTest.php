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
     * @expectedExceptionMessage Config is missing the following keys: api_key, api_secret
     */
    public function testMissingRequiredParametersThrowsException()
    {
        OoyalaClient::factory();
    }

    /**
     * @expectedException Guzzle\Common\Exception\InvalidArgumentException
     * @expectedExceptionMessage Config is missing the following keys: api_secret
     */
    public function testMissingApiSecretThrowsException()
    {
        OoyalaClient::factory(array('api_key' => '123'));
    }

    /**
     * Test to make sure configuration can override the service definition.
     */
    public function testBaseUrlServiceOverride()
    {
        $baseUrl = 'https://custom-base.com/{api_version}';
        $baseUrlExpected = 'https://custom-base.com/v2';
        $client = OoyalaClient::factory(array(
            'api_key' => '123',
            'api_secret' => '456',
            'api_version' => 'v2',
            'base_url' => $baseUrl,
        ));

        $this->assertEquals($baseUrlExpected, $client->getBaseUrl());
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
     * @group internet
     */
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
        $command = $client->getCommand('GetAssets', array(
            'limit' => 1
        ));

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
        $client = $this->getClient();
        $command = $client->getCommand('GetAssets', array(
            'limit' => 1
        ));

        $beforeSend = function (\Guzzle\Common\Event $event) use ($client) {
            /** @var $request \Guzzle\Http\Message\Request */
            $request =& $event['request'];
            if ($request->getQuery()->hasKey('signature')) {
                // Set an invalid signature
                $request->getQuery()->set('signature', strrev($request->getQuery()->get('signature')));
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
            $this->assertEquals(400, $response->getStatusCode(), 'Invalid signature should return a 400 response code.');
            $client->getEventDispatcher()->removeListener('request.before_send', $beforeSend);

            throw $e;
        }
    }

    /**
     * Test that an invalid API key returns a 401 response.
     *
     * @expectedException Guzzle\Http\Exception\ClientErrorResponseException
     * @group internet
     */
    public function testInvalidApiKeyException()
    {
        $client = $this->getClient();
        $client->getConfig()->set('aki_key', 'your_argument_is_invalid');
        $command = $client->getCommand('GetAssets', array(
            'limit' => 1,
        ));

        $beforeSend = function (\Guzzle\Common\Event $event) {
            /** @var $request \Guzzle\Http\Message\Request */
            $request = $event['request'];
            $request
                ->getQuery()
                ->set('api_key', 'your_argument_is_invalid')
            ;
        };

        $client->getEventDispatcher()->addListener('request.before_send', $beforeSend, -9999999999);

        try {
            $command->execute();
        } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody(true));
            $this->assertEquals(401, $response->getStatusCode());
            $this->assertObjectHasAttribute('message', $body);
            $this->assertEquals('Invalid API key.', $body->message);

            $client->getEventDispatcher()->removeListener('request.before_send', $beforeSend);

            throw $e;
        }

        $this->fail("Invalid 'api_key' parameter should raise '\Guzzle\Http\Exception\ClientErrorResponseException' exception.");
    }

    public function testClientRespectsExistingExpiresParam()
    {
        $client = $this->getMockClient();
        $this->setMockResponse($client, '/Assets/GetAssetsWithMetadataAndLabels');

        $expires = time();

        $command = $client->getCommand('GetAssets', array(
            'expires' => $expires,
        ));

        $command->execute();
        $request = $command->getRequest();

        $this->assertEquals(
            $expires,
            $request->getQuery()->get('expires'),
            "The 'expires' param passed to Client::getCommand() should be used, not the OoyalaClient::onRequestBeforeSend() default."
        );
    }

    /**
     * @group cache
     * @group internet
     */
    public function testCachedResponse()
    {
        $client = $this->getCacheEnabledClient();

        $doRequest = function () use ($client) {
            static $unique;

            if (!$unique) {
                $unique = time();
            }

            $beforeSend = function (\Guzzle\Common\Event $event) use ($unique) {
                /** @var $request \Guzzle\Http\Message\Request */
                $request = $event['request'];
                // Make this unique so it will not match the cache key.
                $request->getQuery()->add('unique', $unique);
            };

            $client->getEventDispatcher()->addListener('request.before_send', $beforeSend);
            $command = $client->getCommand('GetAssets');
            $command->execute();

            $client->getEventDispatcher()->removeListener('request.before_send', $beforeSend);

            return $command;
        };

        /** @var \Guzzle\Service\Command\CommandInterface $return */
        $return = $doRequest();
        $responseHeaders = $return->getResponse()->getHeaders();
        $this->assertEquals('MISS from GuzzleCache', $responseHeaders->get('X-Cache-Lookup'));
        $this->assertEquals('MISS from GuzzleCache', $responseHeaders->get('X-Cache'));
        $firstResponse = (string) $return->getResponse()->getBody();

        // The cache plugin should set the cached response.
        /** @var \Guzzle\Service\Command\CommandInterface $return */
        $cachedResponse = $doRequest();
        $responseHeaders = $cachedResponse->getResponse()->getHeaders();
        $this->assertEquals('HIT from GuzzleCache', $responseHeaders->get('X-Cache-Lookup'));
        $this->assertEquals('HIT from GuzzleCache', $responseHeaders->get('X-Cache'));
        $secondResponse = (string) $cachedResponse->getResponse()->getBody();

        // Assert the same response body
        $this->assertEquals($firstResponse, $secondResponse);
    }
}
