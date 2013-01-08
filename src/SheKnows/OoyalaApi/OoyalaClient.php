<?php

namespace SheKnows\OoyalaApi;

use Guzzle\Service\Client;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Http\Message\Request;
use Guzzle\Common\Event;
use Guzzle\Common\Collection;

class OoyalaClient extends Client
{

    private $apiKey;

    private $apiSecret;

    public static function factory($config = array())
    {
        $defaults = array(
            'base_url' => 'https://api.ooyala.com',
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

        // Lowest priority since all GET params must be included, and perhaps other listeners might add some.
        $client->getEventDispatcher()->addListener('command.before_send', array(&$client, 'onRequestCreate'), -9999);

        return $client;
    }

    public function onRequestCreate(Event $event)
    {
        /** @var $command \Guzzle\Service\Command\CommandInterface */
        $command = $event['command'];
        $request = $command->getRequest();

        $request->getParams()->add('signature', $this->signRequest($request));
    }

    /**
     * Sign the request per Ooyala's specifications
     *
     * @link http://support.ooyala.com/developers/documentation/tasks/api_signing_requests.html
     *
     * @param \Guzzle\Http\Message\Request $request
     *
     * @return string
     */
    final private function signRequest(Request $request)
    {
        $parameters = $request->getParams()->toArray();
        $keys = $this->sortKeys($parameters);

        $to_sign = $this->apiKey . $request->getMethod() . $request->getPath();
        foreach ($keys as $key) {
            $to_sign .= $key . "=" . $parameters[$key];
        }

        // Get the entity body (POST, PUT, PATCH, DELETE)
        if ($request->getMethod() !== Request::GET) {
            $to_sign .= $request->getBody();
        }

        $hash = hash("sha256", $to_sign, true);
        $base = base64_encode($hash);
        $base = substr($base, 0, 43);
        $base = urlencode($base);

        return $base;
    }

    private function sortKeys(array $array)
    {
        $keys = array();
        $ind = 0;

        foreach ($array as $key => $val) {
            $keys[$ind++] = $key;
        }

        sort($keys);

        return $keys;
    }
}