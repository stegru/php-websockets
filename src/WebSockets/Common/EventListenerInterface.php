<?php

namespace WebSockets\Common;


/**
 * Interface to be implemented in order to accept messages from the MessageDispatcher class.
 *
 * @package WebSockets\Server
 */
interface EventListenerInterface
{
    /**
     * Called when a message has been received, or a client has connected/disconnected.
     *
     * @param $event Event The event object.
     * @return mixed
     */
    public function gotEvent(Event $event);
}
