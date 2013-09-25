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
            $this->enabled = true;
            $defaults = array(
                'max-age'        => 900,
                'stale-if-error' => 1800,
                'key_filter'     => 'expires,signature',
                'plugin'         => array(
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
                ),
            );

            $this->config = array_merge($defaults, (array) $config['ooyala.cache']);

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
            'max-age=%i, stale-if-error=%i',
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
