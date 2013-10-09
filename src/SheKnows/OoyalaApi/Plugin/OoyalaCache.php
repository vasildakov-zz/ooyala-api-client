<?php

namespace SheKnows\OoyalaApi\Plugin;

use Guzzle\Common\Event;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\CallbackCanCacheStrategy;
use Guzzle\Plugin\Cache\SkipRevalidation;
use Guzzle\Http\Exception\CurlException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OoyalaCache implements EventSubscriberInterface
{
    private $config = array();

    const CACHE_HEADER_DEFAULT_MAX_AGE  = '900';
    const CACHE_HEADER_STALE_IF_ERROR   = '1800';
    public static $CACHE_SUCCESSFUL_STATUS_CODES = array('200', '304');

    /**
     * @var \Guzzle\Plugin\Cache\CachePlugin
     */
    public $guzzleCachePlugin;

    public static function getSubscribedEvents()
    {
        return array(
            'ooyala.client.initialized' => array('onClientCreated'),
            'request.before_send'       => array('onRequestBeforeSend', -10),
            'request.exception'         => array('onRequestException'),
        );
    }

    public function onClientCreated(Event $event)
    {
        /** @var \Guzzle\Http\ClientInterface $client */
        $client = $event['client'];
        $config = $client->getConfig();

        // If cache is not configured, remove this subscriber
        if (!isset($config['ooyala.cache'])) {
            $client->getEventDispatcher()->removeSubscriber($this);
            return;
        }

        $config = $config['ooyala.cache'];
        $defaults = array(
            'max-age'        => self::CACHE_HEADER_DEFAULT_MAX_AGE,
            'stale-if-error' => self::CACHE_HEADER_STALE_IF_ERROR,
            'key_filter'     => 'expires,signature',

            // Configuration options match \Guzzle\Plugin\Cache\CachePlugin
            // Defaults for Ooyala
            'plugin'         => array(

                // Revalidation currently skipped. Control with max-age in commands.
                // Once Ooyala cache response headers are being sent properly
                // this might not be needed.
                'revalidation' => new SkipRevalidation(),

                // Custom can_cache strategy to deal with a lack of proper response cache headers.
                'can_cache'  => new CallbackCanCacheStrategy(
                    function (Request $request) {
                        return true;
                    },
                    function (Response $response) {
                        $statusCode = $response->getStatusCode();
                        if (in_array($statusCode, OoyalaCache::$CACHE_SUCCESSFUL_STATUS_CODES)) {
                            return true;
                        }

                        return false;
                    }
                )
            ),
        );

        if (isset($config['plugin'])) {
            $config['plugin'] = array_merge($defaults['plugin'], $config['plugin']);
        }

        $this->config = array_merge($defaults, (array) $config);

        $this->guzzleCachePlugin = new CachePlugin($this->getConfig('plugin'));

        // Add Guzzle's CachePlugin subscriber
        $client->addSubscriber($this->guzzleCachePlugin);
    }

    public function onRequestBeforeSend(Event $event)
    {
        /** @var $request \Guzzle\Http\Message\Request */
        $request = $event['request'];

        $request->getParams()->set('cache.key_filter', $this->getConfig('key_filter'));

        if (!$request->hasHeader('Cache-Control')) {
            $request->setHeader('Cache-Control', sprintf(
                'max-age=%d, stale-if-error=%d',
                $this->getConfig('max-age'),
                $this->getConfig('stale-if-error')
            ));
            return;
        }

        $cacheControl = $request->getHeader('Cache-Control');

        foreach (array('max-age', 'stale-if-error') as $directive) {
            if (!$cacheControl->hasDirective($directive)) {
                $cacheControl->addDirective(
                    $directive,
                    $this->getConfig($directive)
                );
            }
        }
    }

    /**
     * This is a very hacked way to listen for a cURL timeout.
     * and serve a cached response from stale cache.
     *
     * The Guzzle\Plugin\Cache\CachePlugin::onBeforeRequestSend event listener
     * method is called manually to allow the plugin to set the cached response.
     *
     * @param Event $event
     */
    public function onRequestException(Event $event)
    {
        $exception = $event['exception'];
        /** @var \Guzzle\Http\Message\Request $request */
        $request =& $event['request'];

        if (!$request) {
            return;
        }

        $cacheControl = $request->getHeader('Cache-Control');

        // Make sure the response can satisfy a request manually before proceeding.
        if (
            $exception instanceof CurlException &&
            $exception->getErrorNo() === CURLE_OPERATION_TIMEOUTED &&
            $this->guzzleCachePlugin->canResponseSatisfyFailedRequest($event['request'], new Response(408))
        ) {
            if (
                $cacheControl &&
                $cacheControl->hasDirective('max-age') &&
                $cacheControl->hasDirective('stale-if-error')
            ) {
                // Hack the max-age so the CachePlugin will accept the request as cacheable.
                $cacheControl->addDirective('max-age', $this->getConfig('stale-if-error'));
                $this->guzzleCachePlugin->onRequestBeforeSend(new Event(array(
                    'request' => $request
                )));
            }
        }
    }

    public function getConfig($key)
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        return null;
    }
}
