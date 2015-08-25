<?php
namespace WebSockets\Server;

/**
 * A connection request to the server, performs the WebSocket handshake.
 *
 * @package WebSockets\Server
 */
class WebSocketRequest
{
    /** @var HttpRequest */
    private $httpRequest;
    /** @var HttpResponse */
    private $httpResponse;
    /** @var string */
    private $protocol;
    /** @var string */
    private $key;
    /** @var string[] */
    private $protocols;
    private $rejected = FALSE;

    /**
     * WebSocketRequest constructor.
     * @param HttpRequest $httpRequest The HTTP request.
     * @param HttpResponse $httpResponse The HTTP response. NULL to create a new one.
     */
    public function __construct(HttpRequest $httpRequest, HttpResponse $httpResponse = NULL)
    {
        $this->httpRequest = $httpRequest;
        $this->httpResponse = $httpResponse ?: new HttpResponse();
    }

    /**
     * Read and validate the request.
     * @return bool TRUE if valid.
     */
    public function validate()
    {
        $requestLine = $this->httpRequest->getRequestLine();

        if (preg_match(',^GET /[^ ]* HTTP/1.1$,', $requestLine) !== 1) {
            $this->httpResponse->setStatus("400 Bad Request");

        } else if (stristr($this->httpRequest->getHeader("Upgrade"), "websocket") === FALSE) {
            $this->reject("400 Unrecognised Upgrade header");

        } else if (stristr($this->httpRequest->getHeader("Connection"), "Upgrade") === FALSE) {
            $this->reject("400 Unrecognised Connection header");

        } else if ($this->httpRequest->getHeader("Sec-WebSocket-Version") !== "13") {
            $this->reject("400 Unrecognised Sec-WebSocket-Version header");

        } else if (($this->key = $this->httpRequest->getHeader("Sec-WebSocket-Key")) === NULL) {
            $this->reject("400 Unrecognised Sec-WebSocket-Key");

        } else if (($protocols = $this->httpRequest->getHeader("Sec-WebSocket-Protocol")) === NULL) {
            $this->reject("400 Unrecognised Sec-WebSocket-Protocol");

        } else {
            $this->protocols = explode(',', $protocols);
            $this->protocol = $this->protocols[0];
            $this->httpResponse->setStatus("101 Switching Protocols");
            return TRUE;
        }

        return FALSE;
    }

    public function handshake()
    {
        $clientKey = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $hash = base64_encode(sha1($this->key . $clientKey, true));

        $this->httpResponse->addHeaders([
            "Upgrade" => "websocket",
            "Connection" => "Upgrade",
            "Sec-WebSocket-Accept" => $hash,
            "Sec-WebSocket-Protocol" => $this->protocol
        ]);
    }

    /**
     * Rejects the request.
     *
     * @param string $message
     * @param int $httpResponseCode
     */
    public function reject($message = "Rejected", $httpResponseCode = 400)
    {
        $this->httpResponse->setStatus($httpResponseCode . ' ' . $message);
    }

    /**
     * Gets the HTTP request.
     *
     * @return HttpRequest
     */
    public function getHttpRequest()
    {
        return $this->httpRequest;
    }

    /**
     * Gets the HTTP response that will be sent back to the client.
     *
     * @return HttpResponse
     */
    public function getHttpResponse()
    {
        return $this->httpResponse;
    }

    /**
     * Gets the selected protocol.
     *
     * @return string
     */
    public function getProtocol()
    {
        return $this->protocol;
    }

    /**
     * Sets the protocol.
     *
     * @param string $protocol The protocol to use. This must be in the list of available protocols.
     */
    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
    }

    /**
     * Gets the list of available protocols that the client supports.
     *
     * @return \string[]
     */
    public function getProtocols()
    {
        return $this->protocols;
    }

    /**
     * Returns TRUE if the request is being rejected.
     *
     * @return boolean
     */
    public function isRejected()
    {
        return $this->rejected;
    }


}