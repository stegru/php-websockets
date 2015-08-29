<?php

namespace WebSockets\Common;

/**
 * Dispatches events.
 *
 * @package WebSockets\Server
 */
trait EventDispatcherTrait
{
    /** @var EventListenerInterface[]|callable[] */
    private $eventListeners = [];

    /**
     * Adds a event listener to the subscribed list.
     *
     * @param EventListenerInterface|callable $listener
     */
    public function addEventListener($listener)
    {
        $this->eventListeners[] = $listener;
    }

    /**
     * Un-subscribe a event listener.
     *
     * @param EventListenerInterface|callable $listener
     */
    public function removeEventListener($listener)
    {
        $key = array_search($listener, $this->eventListeners, false);
        if ($key !== false) {
            unset($this->eventListeners[$key]);
        }
    }

    /**
     * Notify the event listeners that there's a event, or connection is opened or closed.
     *
     * @param Event $event The event object.
     */
    public function notifyEventListeners(Event $event)
    {
        $event->setSent();
        foreach ($this->eventListeners as $listener) {
            if ($listener instanceof EventListenerInterface) {
                $listener->gotEvent($event);
            } else {
                if (is_callable($listener)) {
                    $listener($event);
                }
            }
        }
    }

    /**
     * Creates an event object.
     *
     * @param null $eventId
     * @return Event A new event.
     */
    protected function createEvent($eventId = null)
    {
        $event = new Event($this, $eventId);

        return $event;
    }
}
