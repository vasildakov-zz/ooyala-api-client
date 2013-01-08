<?php

namespace SheKnows\OoyalaApi\Tests;

use Guzzle\Tests\GuzzleTestCase;

class OoyalaClientTest extends GuzzleTestCase
{
    /**
     * @var \SheKnows\Ooyala\ApiClient\OoyalaClient
     */
    private $client;

    public function testValidClient()
    {
        $this->assertInstanceOf('\SheKnows\OoyalaApi\OoyalaClient', $this->client);
    }

    protected function setUp()
    {
        $this->client = $this->getServiceBuilder()->get('test.ooyala-client');
    }
}