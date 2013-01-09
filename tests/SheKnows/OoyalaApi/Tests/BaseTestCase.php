<?php

namespace SheKnows\OoyalaApi\Tests;

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
        return $this->getServiceBuilder()->get('test.ooyala-client');
    }

}