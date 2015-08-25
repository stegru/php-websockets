<?php

namespace tests;


use WebSockets\Server\HttpRequest;

/**
 * Tests for the HttpRequestTest class.
 *
 * @package tests
 */
class HttpRequestTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Builds a request string.
     *
     * @param string $requestLine
     * @param array $headerArray
     *
     * @return string The full request.
     */
    public static function BuildRequest($requestLine, $headerArray)
    {
        $headerString = $requestLine . "\r\n";

        foreach ($headerArray as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }

        return $headerString . "\r\n";
    }

    /**
     * Tests the HTTP headers are parsed correctly.
     */
    public function testParseHeaders()
    {
        $requestLine = "GET / HTTP/1.1";

        $headerArray = [
            "Header1" => "Value1",
            "Header2" => "Value2",
            "Header3" => "Value3",
            "Header4" => "Value4",
        ];

        $requestString = self::BuildRequest($requestLine, $headerArray);

        $req = HttpRequest::FromString($requestString);

        self::assertEquals($headerArray, $req->getHeaders());
        self::assertEquals($requestLine, $req->getRequestLine());
    }
}
