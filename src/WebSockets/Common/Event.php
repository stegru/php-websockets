<?php

namespace WebSockets\Common;

use WebSockets\Server\WebSocketRequest;

/**
 * @property string eventId The type of event.
 * @property object producer The object that produced this event.
 *
 * @property Connection connection The connection.
 *
 * @property mixed message The message. (EVENT_MESSAGE only)
 *
 * @property WebSocketRequest request The request sent during the handshake. (EVENT_HANDSHAKE only)
 *
 * @package WebSockets\Common
 */
class Event
{
    /** @var bool */
    private $sent = false;
    private $readonly = false;
    /** @var array Properties of this object. */
    private $properties = [];

    /**
     * Event constructor.
     *
     * @param object $producer The object that's producing this event.
     * @param string $eventId The event identifier.
     */
    public function __construct($producer, $eventId)
    {
        $this->eventId = $eventId;
        $this->producer = $producer;
    }

    /**
     * is utilized for reading data from inaccessible members.
     *
     * @param $name string
     * @return mixed
     * @link http://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members
     */
    function __get($name)
    {
        return $this->__isset($name) ? $this->properties[$name] : null;
    }

    /**
     * run when writing data to inaccessible members.
     *
     * @param $name string
     * @param $value mixed
     * @return void
     * @link http://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members
     */
    function __set($name, $value)
    {
        if (!$this->readonly) {
            $this->properties[$name] = $value;
        }
    }

    /**
     * is triggered by calling isset() or empty() on inaccessible members.
     *
     * @param $name string
     * @return bool
     * @link http://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members
     */
    function __isset($name)
    {
        return array_key_exists($name, $this->properties) && $this->properties[$name] != null;
    }

    /**
     * is invoked when unset() is used on inaccessible members.
     *
     * @param $name string
     * @return void
     * @link http://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members
     */
    function __unset($name)
    {
        if (!$this->readonly) {
            unset($this->properties[$name]);
        }
    }

    /**
     * Specifies that this message has been sent.
     */
    public function setSent()
    {
        $this->readonly = $this->sent = true;
    }
}

