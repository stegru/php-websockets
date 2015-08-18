<?php

namespace WebSockets\Server;

/**
 * A Http response.
 *
 * @package WebSockets\Server
 */
class HttpResponse
{
    /** @var string */
    private $status;
    private $headers = [];

    /**
     * Returns the string representation of this class.
     *
     * @return string
     */
    public function __toString()
    {
        $output = "HTTP/1.1 $this->status\r\n";
        foreach ($this->headers as $name => $value) {
            $output .= "$name: $value\r\n";
        }

        return "$output\r\n";
    }

    /**
     * Gets the headers.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Sets a header.
     *
     * @param string $name
     * @param string $value
     */
    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    /**
     * Adds an array of headers.
     *
     * @param $headers
     */
    public function addHeaders($headers)
    {
        $this->headers = array_merge($this->headers, $headers);
    }

    /**
     * Gets the status line.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Sets the status line.
     *
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }
}

