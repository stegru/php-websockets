<?php

namespace WebSockets\Common;


/**
 * Interface to be implemented in order to accept messages from the MessageDispatcher class.
 *
 * @package WebSockets\Server
 */
interface MessageListenerInterface
{
    /**
     * Called when a message has been received, or a client has connected/disconnected.
     *
     * @param Connection $connection The client connection.
     * @param string $message The message text. NULL if the client has connected or disconnected.
     * @return mixed
     */
    public function gotMessage(Connection $connection, $message);
}
