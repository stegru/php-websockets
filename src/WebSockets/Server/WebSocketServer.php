<?php

namespace WebSockets\Server;
use WebSockets\Common\Connection;
use WebSockets\Common\Frame;
use WebSockets\Common\MessageDispatcherTrait;
use WebSockets\Common\MessageListenerInterface;
use WebSockets\Common\SocketReader;
use WebSockets\Common\StreamListenerInterface;

/**
 * Class WebSocketServer
 *
 * The server for WebSockets.
 *
 * @package WebSockets\Server
 */
class WebSocketServer implements StreamListenerInterface, MessageListenerInterface
{
    use MessageDispatcherTrait;

    /** @var SocketReader */
    private $streams;
    /** @var ClientConnection[] */
    private $connections = [];
    /** @var int The address to listen on. */
    private $bindAddress;

    /**
     * Initialises the WebSocketServer class.
     *
     * @param $bindAddress string The IP:port or just the port to listen on.
     */
    public function __construct($bindAddress = "0.0.0.0:8088")
    {
        $this->bindAddress = is_numeric($bindAddress) ? "0.0.0.0:$bindAddress" : $bindAddress;

        $this->streams = new SocketReader($this);
    }

    /**
     * Starts the server, and should not return.
     *
     * @return bool FALSE on error.
     */
    public function start()
    {
        $this->createListener();

        while ($this->pump(NULL) === TRUE);

        return FALSE;
    }

    /**
     * Pumps any pending data.
     *
     * @param int $timeout Number of seconds to wait for something. NULL to wait forever.
     * @return bool TRUE on timeout, FALSE on error.
     */
    public function pump($timeout = 30)
    {
        return $this->streams->listen($timeout);
    }

    /**
     * Creates the listening socket.
     */
    private function createListener()
    {
        $listen = stream_socket_server("tcp://$this->bindAddress");
        $this->streams->addStream($listen, 'listener');
    }

    /**
     * Accepts a new connection.
     *
     * @param resource $listenerStream The socket created by stream_socket_server
     */
    private function newConnection($listenerStream)
    {
        $stream = stream_socket_accept($listenerStream);

        // The first bit of data from the client should be a HTTP request.
        $req = HttpRequest::FromStream($stream, [$this, 'gotHeaders']);

        // Send the stream to the HTTP request handler.
        $this->streams->addStream($stream, NULL, $req);
    }

    /**
     * Called when the HTTP request headers have been received.
     *
     * @param HttpRequest $req
     * @param $success
     */
    public function gotHeaders(HttpRequest $req, $success)
    {
        $stream = $req->getStream();
        $streamId = $this->streams->getId($stream);

        // Remove the stream from the list, because the HttpRequest is listening to it.
        $this->streams->removeStream($streamId);

        if ($success) {
            $response = $this->performHandshake($req);
            $success = substr($response->getStatus(), 0, 3) === "101";
            fwrite($stream, $response);
        }

        if (!$success) {
            fclose($stream);
            return;
        }

        // Send the stream events to this class.
        $streamId = $this->streams->addStream($stream, $streamId, $this);

        $connection = new ClientConnection($stream, $streamId);
        $connection->addMessageListener($this);
        $this->connections[$streamId] = $connection;
        $this->notifyMessageListeners($connection, NULL);
    }

    /**
     * Performs the WebSocket HTTP hand-shake.
     *
     * @param HttpRequest $req The complete HTTP request.
     * @return HttpResponse The HTTP response to send back to the browser.
     */
    private function performHandshake(HttpRequest $req)
    {
        $response = new HttpResponse();
        $requestLine = $req->getRequestLine();

        if (preg_match(',^GET /[^ ]* HTTP/1.1$,', $requestLine) !== 1) {
            $response->setStatus("400 Bad Request");

        } else if (stristr($req->getHeader("Upgrade"), "websocket") === FALSE) {
            $response->setStatus("400 Unrecognised Upgrade header");

        } else if (stristr($req->getHeader("Connection"), "Upgrade") === FALSE) {
            $response->setStatus("400 Unrecognised Connection header");

        } else if ($req->getHeader("Sec-WebSocket-Version") !== "13") {
            $response->setStatus("400 Unrecognised Sec-WebSocket-Version header");

        } else if (($key = $req->getHeader("Sec-WebSocket-Key")) === NULL) {
            $response->setStatus("400 Unrecognised Sec-WebSocket-Key");

        } else if (($protocol = $req->getHeader("Sec-WebSocket-Protocol")) === NULL) {
            $response->setStatus("400 Unrecognised Sec-WebSocket-Protocol");

        } else {

            $clientKey = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
            $hash = base64_encode(sha1("$key$clientKey", true));

            $response->setStatus("101 Switching Protocols");
            $response->addHeaders([
                "Upgrade" => "websocket",
                "Connection" => "Upgrade",
                "Sec-WebSocket-Accept" => $hash,
                "Sec-WebSocket-Protocol" => $protocol
            ]);
        }

        return $response;
    }

    /**
     * Gets a connection.
     *
     * @param string $id The ID of the connection.
     * @return null|ClientConnection
     */
    private function getConnection($id)
    {
        return isset($this->connections[$id]) ? $this->connections[$id] : NULL;
    }

    /**
     * Sends some data to every client.
     *
     * @param string $data
     */
    public function broadcastData($data)
    {
        foreach ($this->connections as $connection) {
            $connection->sendData($data);
        }
    }

    /**
     * Sends a message to every client.
     *
     * @param $message
     */
    public function broadcastMessage($message)
    {
        $this->broadcastData(Frame::FromMessage($message)->getData());
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
        if ($id === 'listener') {
            $this->newConnection($stream);
            return;
        }

        $buf = fread($stream, 0x400);

        $connection = $this->getConnection($id);
        $connection->gotData($buf);
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
        $connection = $this->getConnection($id);
        $connection->connectionClosed();
        unset($this->connections[$id]);
    }

    /**
     * Called when a message has been received, or a client has connected/disconnected.
     *
     * @param Connection $clientConnection The client connection.
     * @param string $message The message text. NULL if the client has connected or disconnected.
     * @return mixed
     */
    public function gotMessage(Connection $clientConnection, $message)
    {
        $this->notifyMessageListeners($clientConnection, $message);
    }
}
