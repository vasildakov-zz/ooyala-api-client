<?php

namespace SheKnows\OoyalaApi;

use SheKnows\OoyalaApi\Plugin\OoyalaSignature;

use Guzzle\Service\Client;
use Guzzle\Common\Event;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Common\Collection;

class OoyalaClient extends Client
{

    private $apiKey;

    private $apiSecret;

    public static function factory($config = array())
    {
        $defaults = array(
            'base_url' => 'https://api.ooyala.com/{api_version}',
            'api_version' => 'v2',
        );

        $required = array('api_key', 'api_secret');
        $config = Collection::fromConfig($config, $defaults, $required);
        $client = new self($config->get('base_url'), $config);

        // Set key/secret for convenience
        $client->apiKey = $config->get('api_key');
        $client->apiSecret = $config->get('api_secret');

        // Service description
        $apiVersion = $config->get('api_version');
        $description = ServiceDescription::factory(__DIR__ . "/client-{$apiVersion}.json");
        $client->setDescription($description);

        $client->getEventDispatcher()->addListener('request.before_send', array(&$client, 'onRequestBeforeSend'), 0);

        // OoyalaSignature plugin for singing requests.
        $client->addSubscriber(new OoyalaSignature($client->apiSecret));

        return $client;
    }

    /**
     * Set required 'api_key' and 'expires' params.
     *
     * @param \Guzzle\Common\Event $event
     */
    public function onRequestBeforeSend(Event $event)
    {
        /** @var $request \Guzzle\Http\Message\Request */
        $request = $event['request'];

        $request->getQuery()
            ->set('api_key', $this->apiKey)
            ->set('expires', strtotime('+15 minutes'))
        ;
    }
}