<?php

namespace WebSockets\Common;


/**
 * Dispatches messages.
 *
 * @package WebSockets\Server
 */
trait MessageDispatcherTrait
{
    /** @var MessageListenerInterface[] */
    private $messageListeners = [];

    /**
     * Adds a message listener to the subscribed list.
     *
     * @param MessageListenerInterface $listener
     */
    public function addMessageListener(MessageListenerInterface $listener)
    {
        $this->messageListeners[] = $listener;
    }

    /**
     * Un-subscribe a message listener.
     *
     * @param MessageListenerInterface $listener
     */
    public function removeMessageListener(MessageListenerInterface $listener)
    {
        $key = array_search($listener, $this->messageListeners, FALSE);
        if ($key !== FALSE) {
            unset($this->messageListeners[$key]);
        }
    }

    /**
     * Notify the message listeners that there's a message, or connection is opened or closed.
     *
     * @param Connection $connection
     * @param $message string|NULL The message body, or NULL if the client has connected/disconnected (see ClientConnection->closed())
     */
    public function notifyMessageListeners(Connection $connection, $message = NULL)
    {
        foreach ($this->messageListeners as $listener) {
            $listener->gotMessage($connection, $message);
        }
    }
}
