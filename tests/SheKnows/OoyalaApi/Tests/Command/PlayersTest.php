<?php

namespace SheKnows\OoyalaApi\Tests\Command;

use SheKnows\OoyalaApi\Tests\BaseTestCase;

/**
 * Tests for the Player API commands
 */
class PlayersTest extends BaseTestCase
{
    /**
     * @group internet
     */
    public function testGetPlayers()
    {
        $client = $this->getClient();
        $command = $client->getCommand('GetPlayers');
        $response = $command->execute();

        $this->assertArrayHasKey('items', $response);
        $this->assertGreaterThan(0, count($response['items']));
        $this->assertArrayHasKey('id', $response['items'][0]);
    }
}