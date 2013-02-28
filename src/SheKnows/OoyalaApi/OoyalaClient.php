<?php

namespace SheKnows\OoyalaApi;

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
     * @param array $config Collection settings. The `api_key` and `api_secret` config values are required.
     *
     * @return \Guzzle\Service\Client|OoyalaClient Instance of the Ooyala client.
     */
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

        $client->getEventDispatcher()->addListener('command.before_send', array(&$client, 'onCommandBeforeSend'), 0);

        // OoyalaSignature plugin for singing requests.
        $client->addSubscriber(new OoyalaSignature($client->apiSecret));

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
    }
}