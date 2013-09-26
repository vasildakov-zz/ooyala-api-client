<?php

namespace SheKnows\OoyalaApi\Plugin;

use Guzzle\Common\Event;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\CallbackCanCacheStrategy;
use Guzzle\Plugin\Cache\SkipRevalidation;
use \Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OoyalaCachePlugin implements EventSubscriberInterface
{
    private $config = array();
    private $enabled = false;

    const CACHE_HEADER_DEFAULT_MAX_AGE  = '900';
    const CACHE_HEADER_STALE_IF_ERROR   = '1800';
    public static CACHE_SUCCESSFUL_STATUS_CODES = array('200', '304');

    public static function getSubscribedEvents()
    {
        return array(
            'ooyala.client.initialized' => array('onClientCreated'),
            'command.before_send'       => array('onCommandBeforeSend'),
        );
    }

    public function onClientCreated(Event $event)
    {
        /** @var \Guzzle\Http\ClientInterface $client */
        $client = $event['client'];
        $config = $client->getConfig();

        if (isset($config['ooyala.cache'])) {
            $config = $config['ooyala.cache'];
            $this->enabled = true;
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
                            if (in_array($statusCode, self::SUCCESSFUL_STATUS_CODES)) {
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

            // Add Guzzle's CachePlugin subscriber
            $client->addSubscriber(new CachePlugin($this->getConfig('plugin')));
        }
    }

    public function onCommandBeforeSend(Event $event)
    {
        if (!$this->isEnabled()) {
            return;
        }

        /** @var $command \Guzzle\Service\Command\OperationCommand */
        $command = $event['command'];
        /** @var $request \Guzzle\Http\Message\Request */
        $request = $command->getRequest();

        $request->setHeader('Cache-Control', sprintf(
            'max-age=%d, stale-if-error=%d',
            $this->getConfig('max-age'),
            $this->getConfig('stale-if-error')
        ));

        $request->getParams()->set('cache.key_filter', $this->getConfig('key_filter'));
    }

    private function getConfig($key)
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        return null;
    }

    private function isEnabled()
    {
        return $this->enabled;
    }
}
