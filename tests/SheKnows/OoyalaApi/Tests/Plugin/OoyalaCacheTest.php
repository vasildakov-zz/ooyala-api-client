<?php

namespace SheKnows\OoyalaApi\Tests\Plugin;

use Guzzle\Http\Client;
use Guzzle\Common\Event;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;

use SheKnows\OoyalaApi\Tests\BaseTestCase;
use SheKnows\OoyalaApi\Plugin\OoyalaCache;
use SheKnows\OoyalaApi\OoyalaClient;

class OoyalaCacheTest extends BaseTestCase
{
    /**
     * Test that the OoyalaCache subscriber is removed if no configuration is provided.
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
     * Sanity check to make sure that OoyalaCache is a subscriber.
     *
     * The only reason this is needed is because the plugin might be disabled
     * because of configuration, so it's good to check when some configuration
     * is present that the plugin actually does not remove itself as a subscriber.
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
     * Make sure that the onCommandBeforeSend method actually sets Cache-Control headers.
     *
     * Test uses the default configured client values.
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

    /**
     * Test that existing request Cache-Control headers are respected.
     *
     * When other listeners or requests set the Cache-Control directives,
     * the OoyalaCache subscriber should skip setting those directives.
     */
    public function test_onRequestBeforeSend_respects_cache_control_header()
    {
        $client = $this->getCacheEnabledClient();
        $beforeSend = function (Event $event) {
            $request = $event['request'];
            $request->setHeader('Cache-Control', 'max-age=10');
        };

        $client->getEventDispatcher()->addListener('request.before_send', $beforeSend, 0);

        $command = $client->getCommand('GetAssets');
        $command->prepare();
        $request = $command->getRequest();
        $request->setState(Request::STATE_TRANSFER);

        $this->assertTrue($request->hasHeader('Cache-Control'));
        $cacheControl = $request->getHeader('Cache-Control');

        foreach (array('max-age' => 10, 'stale-if-error' => 3600) as $directive => $value) {
            $this->assertTrue($cacheControl->hasDirective($directive));
            $this->assertEquals($value, $cacheControl->getDirective($directive));
        }
    }


    /**
     * @group offline
     */
    public function test_onRequestError_caching()
    {
        $client = $this->getCacheEnabledClient();

        /**
         * Make sure the eventual response will be "cacheable"
         * Mocked data will be out-of-date so new
         * `Last-Modified` and `Date` headers are needed.
         */
        $requestSent = function (Event $event) {
            /** @var \Guzzle\Http\Message\Response $response */
            $response = $event['response'];
            $date = new \DateTime('-10 seconds');
            $value = $date->format(\DateTime::RFC2822);
            $response
                ->setHeader('Last-Modified', $value)
                ->setHeader('Date', $value)
            ;
        };

        $client->getEventDispatcher()->addListener('request.sent', $requestSent, 1000);

        $this->setMockResponse($client, '/Assets/GetAssetsWithMetadataAndLabels');
        $command = $client->getCommand('GetAssets');
        $command->execute();
        $response = $command->getResponse();

        $client->getEventDispatcher()->removeListener('request.sent', $requestSent);

        $this->assertEquals('MISS from GuzzleCache', $response->getHeader('X-Cache'));
        $this->assertEquals('MISS from GuzzleCache', $response->getHeader('X-Cache-Lookup'));

        /**
         * Test stale responses being served in the event of an error.
         */

        $self = $this;
        $assertHitError = function (Response $response) use ($self) {
            $self->assertEquals(
                'HIT_ERROR from GuzzleCache',
                $response->getHeader('X-Cache'),
                'X-Cache should be a `HIT_ERROR from GuzzleCache`'
            );
            $self->assertEquals(
                'HIT from GuzzleCache',
                $response->getHeader('X-Cache-Lookup'),
                'X-Cache-Lookup should be `HIT FROM GuzzleCache`'
            );
        };

        // Make CachePlugin skip the stored response from the warmed cached.
        // Needed so that a 4xx response will be triggered from the mock.
        $client->getEventDispatcher()->addListener('request.before_send', function (Event $event) {
            $request = $event['request'];
            $request->setHeader('Cache-Control', 'max-age=0, stale-if-error=3600');
        }, 0);


        foreach (array('/RateLimitReached', '/500InternelServerError') as $mock) {
            $this->setMockResponse($client, $mock);
            $command = $client->getCommand('GetAssets');
            $command->execute();

            $assertHitError($command->getResponse());
            unset($command);
        }
    }

    /**
     * @group internet
     */
    public function test_request_timeout_serves_stale_response()
    {
        $client = $this->getCacheEnabledClient();
        $command = $client->getCommand('GetAssets');

        $command->execute();
        $response = $command->getResponse();

        unset($command, $response);


        $client->getConfig()->set('request.options', array(
            'timeout' => 0.001,
            'connect_timeout' => 0.001,
        ));

        $client->getEventDispatcher()->addListener('request.before_send', function (Event $event) {
            $request = $event['request'];
            $request->setHeader('Cache-Control', 'max-age=0, stale-if-error=3600');
        }, 0);

        $command = $client->getCommand('GetAssets');
        $command->execute();
        $response = $command->getResponse();
        $this->assertEquals('HIT from GuzzleCache', (string) $response->getHeader('X-Cache'));
        $this->assertEquals('HIT from GuzzleCache', (string) $response->getHeader('X-Cache-Lookup'));
    }
}
