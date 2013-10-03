<?php

namespace SheKnows\OoyalaApi;

use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Service\Client;
use Guzzle\Common\Event;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Common\Collection;

use SheKnows\OoyalaApi\Plugin\OoyalaCache;
use SheKnows\OoyalaApi\Plugin\OoyalaSignature;

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

        $client
            ->setDescription($description)
            ->addSubscriber(new OoyalaSignature($client->apiSecret))
            ->addSubscriber(new OoyalaCache())
            ->dispatch(OoyalaClient::EVENT_INITIALIZED, array('client' => $client))
        ;

        return $client;
    }

    private static function processConfig(array $config = array())
    {
        $defaults = array(
            'base_url' => 'https://api.ooyala.com/{api_version}',
            'api_version' => 'v2',
            'request.options' => array(
                'timeout' => 4,
                'connect_timeout' => 2,
            ),
        );

        $required = array('api_key', 'api_secret');

        return Collection::fromConfig($config, $defaults, $required);
    }
}
