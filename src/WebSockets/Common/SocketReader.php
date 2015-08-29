<?php
namespace WebSockets\Common;

/**
 * Listens on multiple streams.
 *
 * @package WebSockets\Server
 */
class SocketReader
{
    /** @var resource[] */
    private $streams = [];
    /** @var StreamListenerInterface[] */
    private $listeners = [];
    /** @var int */
    private $lastId = 0;
    /** @var StreamListenerInterface */
    private $defaultListener;

    /**
     * @param StreamListenerInterface $defaultListener
     */
    public function __construct($defaultListener = null)
    {
        $this->defaultListener = $defaultListener;
    }

    /**
     * Add a stream.
     *
     * @param resource $stream
     * @param mixed $id
     * @param StreamListenerInterface $eventListener
     * @return int|mixed
     */
    public function addStream($stream, $id = null, StreamListenerInterface $eventListener = null)
    {
        if ($this->idExists($id)) {
            $id = null;
        }

        if ($id === null) {
            $id = $this->generateId();
        }

        $this->streams[$id] = $stream;
        if ($eventListener !== null) {
            $this->listeners[$id] = $eventListener;
        }

        return $id;
    }

    /**
     * Checks if a stream with the given ID exists.
     *
     * @param $id string The ID to check
     * @return bool TRUE if there's a stream with the given ID.
     */
    private function idExists($id)
    {
        return array_key_exists($id, $this->streams);
    }

    /**
     * Generates an ID.
     *
     * @return int
     */
    private function generateId()
    {
        while ($this->idExists(++$this->lastId)) {
            ;
        }

        return $this->lastId;
    }

    /**
     * Gets a stream.
     *
     * @param string $id
     * @return resource NULL if no such ID.
     */
    public function getStream($id)
    {
        return $this->idExists($id) ? $this->streams[$id] : null;
    }

    /**
     * Listens on the streams, invoking the stream listeners accordingly.
     * Returns upon timeout, error, or no streams.
     *
     * @param int $timeout Number of seconds to wait for something. NULL to wait forever.
     * @return bool TRUE on timeout, FALSE on error.
     */
    public function listen($timeout = 10)
    {
        while (count($this->streams) > 0) {
            $read = array_merge([], $this->streams);
            $write = null;
            $except = null;

            $changed = stream_select($read, $write, $except, $timeout);

            if ($changed === false) {
                echo("oops\n");

                return false;
            } else {
                if ($changed === 0) {
                    echo("timed out");

                    return true;
                }
            }

            foreach ($read as $stream) {

                $id = $this->getId($stream);
                $listener = $this->getListener($id);

                if ($listener !== null) {
                    // Invoke the listener.
                    if (feof($stream)) {
                        fclose($stream);
                        $listener->streamClosed($stream, $id);
                        $this->removeStream($id);
                    } else {
                        $listener->streamReady($stream, $id);
                    }
                }
            }
        }

        return false;
    }

    /**
     * Gets the ID of a stream or event listener.
     *
     * @param mixed $obj
     * @return bool|mixed
     */
    public function getId($obj)
    {
        if ($obj instanceof StreamListenerInterface) {
            return array_search($obj, $this->listeners, true);
        } else {
            if (is_resource($obj)) {
                return array_search($obj, $this->streams, true);
            }
        }

        // See if it's an ID.
        return $this->idExists($obj) ? $obj : false;
    }

    /**
     * Gets the StreamListenerInterface for a stream.
     *
     * @param mixed $id
     * @return null|StreamListenerInterface
     */
    public function getListener($id)
    {
        return array_key_exists($id, $this->listeners) ? $this->listeners[$id] : $this->defaultListener;
    }

    /**
     * Removes a stream.
     *
     * @param $id
     */
    public function removeStream($id)
    {
        $id = $this->getId($id);
        if ($id !== false) {
            unset($this->streams[$id], $this->listeners[$id]);
        }
    }
}