<?php
namespace WebSockets\Common;

/**
 * Listens on multiple streams.
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
    public function __construct($defaultListener = NULL)
    {
        $this->defaultListener = $defaultListener;
    }

    /**
     * Checks if a stream with the given ID exists.
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
        while ($this->idExists(++$this->lastId));
        return $this->lastId;
    }

    /**
     * Add a stream.
     *
     * @param resource $stream
     * @param mixed $id
     * @param StreamListenerInterface $eventListener
     * @return int|mixed
     */
    public function addStream($stream, $id = NULL, StreamListenerInterface $eventListener = NULL)
    {
        if ($this->idExists($id)) {
            $id = NULL;
        }

        if ($id === NULL) {
            $id = $this->generateId();
        }

        $this->streams[$id] = $stream;
        if ($eventListener !== NULL) {
            $this->listeners[$id] = $eventListener;
        }

        return $id;
    }

    /**
     * Removes a stream.
     *
     * @param $id
     */
    public function removeStream($id)
    {
        $id = $this->getId($id);
        if ($id !== FALSE) {
            unset($this->streams[$id], $this->listeners[$id]);
        }
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
            return array_search($obj, $this->listeners, TRUE);
        } else if (is_resource($obj)) {
            return array_search($obj, $this->streams, TRUE);
        }

        // See if it's an ID.
        return $this->idExists($obj) ? $obj : FALSE;
    }

    /**
     * Gets a stream.
     *
     * @param string $id
     * @return resource NULL if no such ID.
     */
    public function getStream($id)
    {
        return $this->idExists($id) ? $this->streams[$id] : NULL;
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
            $write = NULL;
            $except = NULL;

            $changed = stream_select($read, $write, $except, $timeout);

            if ($changed === FALSE) {
                echo("oops\n");
                return FALSE;
            } else if ($changed === 0) {
                echo("timed out");
                return TRUE;
            }

            foreach ($read as $stream) {

                $id = $this->getId($stream);
                $listener = $this->getListener($id);

                if ($listener !== NULL) {
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

        return FALSE;
    }
}