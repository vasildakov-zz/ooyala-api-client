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

        // Lowest priority since all GET params must be included, and perhaps other listeners might add some.
        $client->getEventDispatcher()->addListener('request.before_send', array(&$client, 'onRequestBeforeSend'), -9999);

        return $client;
    }

    public function onRequestBeforeSend(Event $event)
    {
        /** @var $request \Guzzle\Http\Message\Request */
        $request = $event['request'];

        $request->getQuery()
            ->set('api_key', $this->apiKey)
            ->set('expires', strtotime('+15 minutes'))
        ;

        // Sign the request
        $this->signRequest($request);
    }

    /**
     * Sign the request per Ooyala's specifications
     *
     * @link http://support.ooyala.com/developers/documentation/tasks/api_signing_requests.html
     * @link http://support.ooyala.com/developers/documentation/api/signature_php.html
     *
     * @param \Guzzle\Http\Message\Request $request
     *
     *
     * @return string Signature hash derived from the Request.
     */
    final public function signRequest(Request $request)
    {
        $hash = $this->hashSignature($this->getRawSignature($request));
        $request->getQuery()->set('signature', $hash);
    }

    final public function getRawSignature(Request $request)
    {
        $parameters = $request->getQuery()->toArray();
        $keys = $this->sortKeys($parameters);

        $to_sign = $this->apiSecret . $request->getMethod() . $request->getPath();
        foreach ($keys as $key) {
            $to_sign .= $key . "=" . $parameters[$key];
        }

        // Get the entity body (POST, PUT, PATCH, DELETE)
        if ($request->getMethod() !== Request::GET) {
            $to_sign .= $request->getBody();
        }

        return $to_sign;
    }

    final public function hashSignature($rawSignature)
    {
        $hash = hash("sha256", $rawSignature, true);
        $base = base64_encode($hash);
        $base = substr($base, 0, 43);

        return $base;
    }

    /**
     * Convenience function to sort request parameter keys in order.
     * The order of request parameters is important to generating a valid request signature.
     *
     * @param array $array
     *
     * @return array
     */
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