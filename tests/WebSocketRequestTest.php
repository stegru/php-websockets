<?php

namespace tests;

use WebSockets\Server\HttpRequest;
use WebSockets\Server\WebSocketRequest;

/**
 * Tests for the WebSocketRequestTest class.
 *
 * @package tests
 */
class WebSocketRequestTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Tests a good handshake.
     */
    public function testValidateGoodRequest()
    {
        // from the RFC
        $requestString = <<<REQ
GET / HTTP/1.1
Host: server.example.com
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==
Origin: http://example.com
Sec-WebSocket-Protocol: chat, superchat
Sec-WebSocket-Version: 13

REQ;
;

        $req = HttpRequest::FromString($requestString);
        $webSocketRequest = new WebSocketRequest($req);

        self::assertTrue($webSocketRequest->validate());

    }
}
