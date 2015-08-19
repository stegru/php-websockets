<?php

namespace WebSockets\Common;


/**
 * Dispatches messages.
 *
 * @package WebSockets\Server
 */
trait MessageDispatcherTrait
{
    /** @var MessageListenerInterface[]|callable[] */
    private $messageListeners = [];

    /**
     * Adds a message listener to the subscribed list.
     *
     * @param MessageListenerInterface|callable $listener
     */
    public function addMessageListener($listener)
    {
        $this->messageListeners[] = $listener;
    }

    /**
     * Un-subscribe a message listener.
     *
     * @param MessageListenerInterface|callable $listener
     */
    public function removeMessageListener($listener)
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
            if ($listener instanceof MessageListenerInterface) {
                $listener->gotMessage($connection, $message);
            } else if (is_callable($listener)) {
                $listener($connection, $message);
            }
        }
    }
}
