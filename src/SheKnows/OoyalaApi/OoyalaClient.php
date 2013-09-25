<?php

namespace SheKnows\OoyalaApi;

use Doctrine\Common\Cache\FilesystemCache;
use Guzzle\Cache\DoctrineCacheAdapter;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\CallbackCanCacheStrategy;
use Guzzle\Plugin\Cache\DefaultCacheStorage;
use Guzzle\Plugin\Cache\SkipRevalidation;
use SheKnows\OoyalaApi\Plugin\OoyalaCachePlugin;
use SheKnows\OoyalaApi\Plugin\OoyalaSignature;

use Guzzle\Service\Client;
use Guzzle\Common\Event;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Common\Collection;

/**
 * Ooyala Http client.
 *
 * @api
 */
class OoyalaClient extends Client
{

    /**
     * OoyalaClient initialization event
     *
     * Allows 1st party plugins to add configuration.
     */
    const EVENT_INITIALIZED = 'ooyala.client.initialized';

    /**
     * Ooyala API key.
     *
     * @var string
     */
    private $apiKey;

    /**
     * Ooyala API Secret.
     *
     * @var string
     */
    private $apiSecret;

    /**
     * Factory method for creating a new client.
     *
     * @param array $config  Collection settings. The `api_key` and `api_secret` config values are required.
     *
     * @return Client|OoyalaClient
     */
    public static function factory($config = array())
    {
        $config = self::processConfig($config);
        $client = new self($config->get('base_url'), $config);

        // Set key/secret for convenience
        $client->apiKey = $config->get('api_key');
        $client->apiSecret = $config->get('api_secret');

        // Service description
        $apiVersion = $config->get('api_version');
        $description = ServiceDescription::factory(__DIR__ . "/client-{$apiVersion}.json");
        $client->setDescription($description);

        $client->getEventDispatcher()->addListener('command.before_send', array(&$client, 'onCommandBeforeSend'), 0);

        // OoyalaSignature plugin for singing requests.
        $client->addSubscriber(new OoyalaSignature($client->apiSecret));
        $client->addSubscriber(new OoyalaCachePlugin());

        $client->dispatch(OoyalaClient::EVENT_INITIALIZED, array(
            'client' => $client,
        ));

        return $client;
    }

    /**
     * Event listener to set required 'api_key' and 'expires' params before sending the request.
     *
     * @param \Guzzle\Common\Event $event A `command.before_send` event.
     */
    public function onCommandBeforeSend(Event $event)
    {
        /** @var $command \Guzzle\Service\Command\OperationCommand */
        $command = $event['command'];
        /** @var $request \Guzzle\Http\Message\Request */
        $request = $command->getRequest();

        $query = $request->getQuery();
        $query->set('api_key', $this->apiKey);

        if (!$command->hasKey('expires')) {
            $query->set('expires', strtotime('+15 minutes'));
        }

        $request->setHeader('Cache-Control', 'max-age=900, stale-if-error=1800');
        $request->getParams()->set('cache.key_filter', 'expires,signature');
        $request->getParams()->set('cache.revalidate', 'never');
    }

    private static function processConfig(array $config = array())
    {
        $defaults = array(
            'base_url' => 'https://api.ooyala.com/{api_version}',
            'api_version' => 'v2',
            'request.options' => array(
                'timeout' => 3,
                'connect_timeout' => 1.5,
            ),
        );

        $required = array('api_key', 'api_secret');

        return Collection::fromConfig($config, $defaults, $required);
    }
}
