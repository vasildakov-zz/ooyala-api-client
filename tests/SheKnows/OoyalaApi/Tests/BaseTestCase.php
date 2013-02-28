<?php

namespace SheKnows\OoyalaApi\Tests;

use SheKnows\OoyalaApi\OoyalaClient;

use Guzzle\Tests\GuzzleTestCase;

abstract class BaseTestCase extends GuzzleTestCase
{
    /**
     * @var \SheKnows\OoyalaApi\OoyalaClient
     */
    private $client;

    protected function getCommand($name, $params)
    {
        return $this->client->getCommand($name, $params);
    }

    protected function setUp()
    {
        static::setMockBasePath(__DIR__ . '/TestData');
    }

    /**
     * @return \Guzzle\Service\ClientInterface
     */
    protected function getClient()
    {
        return $this->getServiceBuilder()->get('live.ooyala-client');
    }

    /**
     * Used to make mock requests.
     *
     * @return \Guzzle\Service\ClientInterface
     */
    protected function getMockClient()
    {
        return $this->getServiceBuilder()->get('mock.ooyala-client');
    }

}