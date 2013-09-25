<?php

namespace SheKnows\OoyalaApi\Plugin;

use SheKnows\OoyalaApi\Signature;
use Guzzle\Http\Message\Request;
use Guzzle\Common\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * OoyalaSignature plugin
 *
 * A plugin to properly sign requests to the Ooyala API.
 *
 * @api
 */
class OoyalaSignature implements EventSubscriberInterface
{
    /**
     * Constructor.
     *
     * @param $apiSecret Ooyala API Secret.
     */
    public function __construct($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * @return array Plugin event subscriptions.
     */
    public static function getSubscribedEvents()
    {
        return array(
            'request.before_send' => array('onRequestBeforeSend', -9999),
            'command.before_send' => array('onCommandBeforeSend', 0),
        );
    }

    /**
     * Sign every request before sending.
     *
     * @param \Guzzle\Common\Event $event The `request.before_send` event object.
     */
    public function onRequestBeforeSend(Event $event)
    {
        /** @var $request \Guzzle\Http\Message\Request */
        $request = $event['request'];

        $signature = new Signature($this->apiSecret, $request);
        $request->getQuery()->set('signature', (string) $signature);
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
        /** @var $query \Guzzle\Http\QueryString */
        $query   = $command->getRequest()->getQuery();
        $apiKey  = $command->getClient()->getConfig('api_key');

        $query->set('api_key', $apiKey);

        if (!$command->hasKey('expires')) {
            $query->set('expires', strtotime('+15 minutes'));
        }
    }
}
