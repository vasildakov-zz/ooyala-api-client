<?php

namespace SheKnows\OoyalaApi\Tests\Command;

use SheKnows\OoyalaApi\Tests\BaseTestCase;

/**
 * Tests for the Asset commands
 */
class AssetTests extends BaseTestCase
{

    public function testValidIncludeParam()
    {
        $client = $this->getMockClient();
        $this->setMockResponse($client, 'Assets/GetAssetsWithMetadataAndLabels');

        $command = $client->getCommand('GetAssets', array(
            'include' => 'metadata',
            'limit'   => 1
        ));

        $data = $command->execute();

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('next_page', $data);
        $this->assertCount(1, $data['items'], 'Limit 1 should yield a count of one item.');
        $this->assertArrayHasKey('metadata', $data['items'][0]);
    }

    /**
     * @expectedException Guzzle\Service\Exception\ValidationException
     */
    public function testInvalidIncludePattern()
    {
        $client = $this->getMockClient();
        $this->setMockResponse($client, 'Assets/GetAssetsWithMetadataAndLabels');

        $command = $client->getCommand('GetAssets', array(
            'include' => 'metadata,invalid_value'
        ));
        $command->execute();
    }

    /**
     * @expectedException Guzzle\Service\Exception\ValidationException
     */
    public function testIncludeWithTrailingCommaRaisesValidationException()
    {
        $client = $this->getMockClient();
        $this->setMockResponse($client, 'Assets/GetAssetsWithMetadataAndLabels');

        $command = $client->getCommand('GetAssets', array(
            'include' => 'metadata,'
        ));
        $command->execute();
    }
}