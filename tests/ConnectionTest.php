<?php

namespace tests;


use PHPUnit_Framework_TestCase;
use WebSockets\Common\Connection;
use WebSockets\Common\Event;
use WebSockets\Common\EventListenerInterface;
use WebSockets\Server\ClientConnection;

require_once('FrameTest.php');

/**
 * Tests for the ConnectionTest class.
 *
 * @package tests
 */
class ConnectionTest extends PHPUnit_Framework_TestCase
{
    /** @var EventListenerStub */
    private $eventListener;

    /**
     * Create a new connection.
     *
     * @return ClientConnection
     */
    private function createConnection()
    {
        $this->eventListener = new EventListenerStub();
        $cli = new ClientConnection(NULL, NULL, NULL);
        $cli->addEventListener($this->eventListener);
        return $cli;
    }

    /**
     * Tests a single frame.
     */
    public function testSingleFrame()
    {
        $cli = $this->createConnection();
        $cli->gotData(hex2bin(FrameTest::HelloFrame));

        self::assertEquals(1, count($this->eventListener->messages));
        self::assertEquals("hello", $this->eventListener->messages[0]);
    }

    /**
     * Tests multiple frames in one block.
     */
    public function testMultipleFrames()
    {
        $cli = $this->createConnection();
        $cli->gotData(hex2bin(FrameTest::HelloFrame . FrameTest::HelloFrame . FrameTest::HelloFrame));

        self::assertEquals(3, count($this->eventListener->messages));
        self::assertEquals("hello", $this->eventListener->messages[0]);
        self::assertEquals("hello", $this->eventListener->messages[1]);
        self::assertEquals("hello", $this->eventListener->messages[2]);
    }


    /**
     * Tests a single frame, sent byte-by-byte.
     */
    public function testSingleFrameSlow()
    {
        $cli = $this->createConnection();

        $data = hex2bin(FrameTest::HelloFrame);
        $len = strlen($data);
        for ($n = 0; $n < $len; $n++) {
            $cli->gotData($data[$n]);
        }

        self::assertEquals(1, count($this->eventListener->messages));
        self::assertEquals("hello", $this->eventListener->messages[0]);
    }

    /**
     * Tests multiple frames, sent byte-by-byte.
     */
    public function testMultipleFramesSlow()
    {
        $cli = $this->createConnection();

        $data = hex2bin(FrameTest::HelloFrame . FrameTest::HelloFrame . FrameTest::HelloFrame);
        $len = strlen($data);
        for ($n = 0; $n < $len; $n++) {
            $cli->gotData($data[$n]);
        }

        self::assertEquals(3, count($this->eventListener->messages));
        self::assertEquals("hello", $this->eventListener->messages[0]);
        self::assertEquals("hello", $this->eventListener->messages[1]);
        self::assertEquals("hello", $this->eventListener->messages[2]);
    }
}


class EventListenerStub implements EventListenerInterface
{
    public $messages = array();

    /**
     * Called when a message has been received, or a client has connected/disconnected.
     *
     * @param $event Event The event object.
     * @return mixed
     */
    public function gotEvent(Event $event)
    {
        $this->messages[] = $event->message;
    }
}
