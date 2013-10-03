<?php

namespace SheKnows\OoyalaApi\Tests\Plugin;

use Guzzle\Http\Client;
use Guzzle\Common\Event;

use Guzzle\Http\Message\Request;
use SheKnows\OoyalaApi\Tests\BaseTestCase;
use SheKnows\OoyalaApi\Plugin\OoyalaCache;
use SheKnows\OoyalaApi\OoyalaClient;

class OoyalaCacheTest extends BaseTestCase
{
    /**
     * Test that the OoyalaCache subscriber is removed if no configuration is provided.
     *
     * @group test
     */
    public function test_cache_configuration_config_missing()
    {
        $client = new Client('http://test.com');
        $plugin = new OoyalaCache();
        $className = get_class($plugin);

        $client->addSubscriber($plugin);
        $client->getEventDispatcher()->dispatch(OoyalaClient::EVENT_INITIALIZED, new Event(array(
            'client' => $client,
        )));

        $subscribedEvents = array_keys(OoyalaCache::getSubscribedEvents());

        foreach ($client->getEventDispatcher()->getListeners() as $eventName => $listeners) {
            if (!in_array($eventName, $subscribedEvents)) {
                continue;
            }

            $actual = array();
            foreach ($listeners as $listener) {
                $actual[] = get_class($listener[0]);
            }

            $this->assertFalse(in_array($className, $actual));
        }
    }

    /**
     * @group test
     */
    public function test_cache_configuration_config_present()
    {
        $client = new Client('http://test.com', array(
            'ooyala.cache' => true,
        ));

        $plugin = new OoyalaCache();
        $className = get_class($plugin);

        $client->addSubscriber($plugin);
        $client->getEventDispatcher()->dispatch(OoyalaClient::EVENT_INITIALIZED, new Event(array(
            'client' => $client,
        )));

        $subscribedEvents = array_keys(OoyalaCache::getSubscribedEvents());

        foreach ($client->getEventDispatcher()->getListeners() as $eventName => $listeners) {
            if (!in_array($eventName, $subscribedEvents)) {
                continue;
            }

            $actual = array();
            foreach ($listeners as $listener) {
                $actual[] = get_class($listener[0]);
            }

            $this->assertTrue(in_array($className, $actual));
        }
    }

    /**
     * @group test
     */
    public function test_onCommandBeforeSend_method()
    {
        $client = $this->getCacheEnabledClient();
        $plugin = new OoyalaCache;

        $command = $client->getCommand('GetAssets');
        $command->prepare();

        $request = $command->getRequest();
        $request->setState(Request::STATE_TRANSFER);

        $this->assertTrue($request->hasHeader('Cache-Control'));
        $cacheControl = $request->getHeader('Cache-Control');

        foreach (array('max-age' => 900, 'stale-if-error' => 3600) as $directive => $value) {
            $this->assertTrue($cacheControl->hasDirective($directive));
            $this->assertEquals($value, $cacheControl->getDirective($directive));
        }
    }
}
