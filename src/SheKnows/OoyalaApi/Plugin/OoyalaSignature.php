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
 */
class OoyalaSignature implements EventSubscriberInterface
{
    public function __construct($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'request.before_send' => array('onRequestBeforeSend', -9999)
        );
    }

    public function onRequestBeforeSend(Event $event)
    {
        /** @var $request \Guzzle\Http\Message\Request */
        $request = $event['request'];

        $signature = new Signature($this->apiSecret, $request);
        $request->getQuery()->set('signature', (string) $signature);
    }
}