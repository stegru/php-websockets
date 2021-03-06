<?php

namespace WebSockets\Server;

use WebSockets\Common\StreamListenerInterface;


/**
 * HTTP Request.
 *
 * @package WebSockets\Common
 */
class HttpRequest implements StreamListenerInterface
{
    /** @var string The current buffer containing the request. */
    private $requestBuffer = '';
    /** @var callable The callback to invoke when all of the headers have been received. */
    private $callback;
    /** @var resource The socket stream from which the request is read. */
    private $stream;
    /** @var array The HTTP headers. */
    private $headers;
    private $requestLine;

    /**
     * Creates a HttpRequest instance using the given stream.
     *
     * @param resource $stream The socket stream from which the request is read
     * @param callable $callback The callback to invoke when all of the headers have been received.
     * @return HttpRequest The HttpRequest instance.
     */
    public static function FromStream($stream, callable $callback)
    {
        $req = new HttpRequest();
        $req->stream = $stream;
        $req->callback = $callback;

        return $req;
    }

    /**
     * Creates a HttpRequest instance using the given string.
     *
     * @param string $requestBuffer The HTTP request.
     * @return HttpRequest
     */
    public static function FromString($requestBuffer)
    {
        $req = new HttpRequest();
        $req->requestBuffer = $requestBuffer;
        $req->gotHeaders();

        return $req;
    }

    /**
     * Called when all headers have been received.
     */
    private function gotHeaders()
    {
        $this->headers = $this->parseHeaders();
        $this->callCallback(true);
    }

    /**
     * Parse the HTTP headers.
     *
     * @return array
     */
    private function parseHeaders()
    {
        $lines = preg_split("/\r?\n/", $this->requestBuffer, null, PREG_SPLIT_NO_EMPTY);
        $this->requestLine = array_shift($lines);

        $headers = [];

        foreach ($lines as $line) {
            if (strlen($line) > 0) {
                $split = explode(":", $line, 2);
                $headers[$split[0]] = trim($split[1]);
            }
        }

        return $headers;
    }

    /**
     * Invoke the callback.
     *
     * @param bool $success FALSE upon error.
     */
    private function callCallback($success)
    {
        if (is_callable($this->callback)) {
            call_user_func($this->callback, $this, $success);
        }
    }

    /**
     * Called when a stream is ready for reading.
     *
     * @param resource $stream
     * @param $id
     * @return mixed
     */
    public function streamReady($stream, $id)
    {
        $data = fread($stream, 0x400);
        if ($data === false) {
            return;
        }

        $len = strlen($this->requestBuffer);

        if ($len > 0) {
            $len--;
        }

        $this->requestBuffer .= $data;
        $end = strpos($this->requestBuffer, "\r\n\r\n", $len);

        if ($end !== false) {
            $this->gotHeaders();
        }
    }

    /**
     * Called when a stream has closed.
     *
     * @param resource $stream
     * @param $id
     * @return mixed
     */
    public function streamClosed($stream, $id)
    {
        $this->callCallback(false);
    }

    /**
     * Returns the array of headers.
     *
     * @return mixed
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Gets the specified header.
     *
     * @param string $name The name of the header.
     * @return null|string
     */
    public function getHeader($name)
    {
        return array_key_exists($name, $this->headers) ? $this->headers[$name] : null;
    }

    /**
     * Gets the stream that the headers are being read from.
     *
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Gets the first line of the HTTP request.
     *
     * @return mixed
     */
    public function getRequestLine()
    {
        return $this->requestLine;
    }
}

