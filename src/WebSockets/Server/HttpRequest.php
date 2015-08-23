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
        $this->callCallback(TRUE);
    }

    /**
     * Parse the HTTP headers.
     *
     * @return array
     */
    private function parseHeaders() {
        $headers = http_parse_headers($this->requestBuffer);
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
        if ($data === FALSE) {
            return;
        }

        $len = strlen($this->requestBuffer);

        if ($len > 0) {
            $len--;
        }

        $this->requestBuffer .= $data;
        $end = strpos($this->requestBuffer, "\r\n\r\n", $len);

        if ($end !== FALSE) {
            $this->gotHeaders();
        }
    }

    /**
     * Called when a stream has closed.
     * @param resource $stream
     * @param $id
     * @return mixed
     */
    public function streamClosed($stream, $id)
    {
        $this->callCallback(FALSE);
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
        return isset($this->headers[$name]) ? $this->headers[$name] : NULL;
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
    public function getRequestLine() {
        return $this->headers[0];
    }
}


if (!function_exists('http_parse_headers')) {

    /**
     * A PHP implementation of http_parse_headers, in case it is not available.
     *
     * @param $raw_headers
     * @return array
     */
    function http_parse_headers($raw_headers) {
        $headers = [];
        $key = '';

        foreach(explode("\n", $raw_headers) as $i => $h) {
            $h = explode(':', $h, 2);

            if (isset($h[1])) {
                if (!isset($headers[$h[0]])) {
                    $headers[$h[0]] = trim($h[1]);
                } elseif (is_array($headers[$h[0]])) {
                    $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
                } else {
                    $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
                }

                $key = $h[0];
            }
            else {
                if (substr($h[0], 0, 1) === "\t") {
                    $headers[$key] .= "\r\n\t" . trim($h[0]);
                } elseif (!$key) {
                    $headers[0] = trim($h[0]);
                }
            }
        }

        return $headers;
    }
}