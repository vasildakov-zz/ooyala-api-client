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
            'request.before_send' => array('onRequestBeforeSend', -9999)
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
}