<?php

namespace WebSockets\Common;


/**
 * Interface to implement to receive the socket notifications from SocketReader.
 *
 * @package WebSockets\Server
 */
interface StreamListenerInterface
{
    /**
     * Called when a stream is ready for reading.
     *
     * @param resource $stream
     * @param $id
     * @return mixed
     */
    public function streamReady($stream, $id);

    /**
     * Called when a stream has closed.
     *
     * @param resource $stream
     * @param $id
     * @return mixed
     */
    public function streamClosed($stream, $id);
}
