<?php

namespace SheKnows\OoyalaApi\Plugin;

use Doctrine\Common\Cache\ArrayCache;
use Guzzle\Cache\DoctrineCacheAdapter;
use Guzzle\Common\Event;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\CallbackCanCacheStrategy;
use Guzzle\Plugin\Cache\DefaultCacheStorage;
use Guzzle\Plugin\Cache\SkipRevalidation;
use \Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OoyalaCachePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'ooyala.client.initialized' => array('onClientCreated'),
        );
    }

    public function onClientCreated(Event $event)
    {
        /** @var \Guzzle\Http\ClientInterface $client */
        $client = $event['client'];
        $config = $client->getConfig();

        if (isset($config['ooyala_cache'])) {
            $this->attachCachePlugin($client, (array) $config['ooyala_cache']);
        }
    }

    private function attachCachePlugin(ClientInterface $client, array $config = array())
    {
        $defaults = array(
            'revalidation' => new SkipRevalidation(),
            'cache_cache'  => new CallbackCanCacheStrategy(
                function (Request $request) {
                    return true;
                },
                function (Response $response) {
                    $statusCode = $response->getStatusCode();
                    if (in_array($statusCode, array('200', '304'))) {
                        return true;
                    }

                    return false;
                }
            )
        );

        $instance = array_merge($defaults, $config);

        $client->addSubscriber(new CachePlugin($instance));
    }
}
