<?php

namespace SheKnows\OoyalaApi\Tests\Plugin;

use SheKnows\OoyalaApi\Tests\BaseTestCase;
use Guzzle\Http\Client;

class OoyalaSignatureTest extends BaseTestCase
{
    /**
     * Ensure that a client request is signed.
     */
    public function testSignatureParameterIsPresent()
    {
        $client = $this->getMockClient();
        $this->setMockResponse($client, 'Assets/GetAssetsWithMetadataAndLabels');

        $command = $client->getCommand('GetAssets');
        $command->execute();
        $request = $command->getRequest();
        $this->assertTrue($request->getQuery()->hasKey('signature'), 'The request should have a signature query parameter.');
        // Plugin event should have hashed the signature too. Just double-check length.
        $this->assertEquals(43, strlen($request->getQuery()->get('signature')));
    }
}