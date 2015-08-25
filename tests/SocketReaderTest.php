<?php
namespace tests;

use WebSockets\Common\SocketReader;

/**
 * Class SocketReaderTest
 * @package tests
 */
class SocketReaderTest extends \PHPUnit_Framework_TestCase
{
    const STREAM_ID = 'stream1';

    private $streams = [];

    private function createStream()
    {
        return $this->streams[] = fopen("php://memory", "w+");
    }

    /**
     * Close any streams that were left open.
     */
    public function tearDown()
    {
        foreach ($this->streams as $stream) {
            fclose($stream);
        }
    }


    /**
     * Tests if the stream ID is returned when adding it.
     */
    public function testAddStream_IdReturned()
    {
        $reader = new SocketReader();
        $stream = $this->createStream();
        $id = $reader->addStream($stream, "stream1");

        self::assertEquals("stream1", $id, "Correct ID returned");
    }

    /**
     * Tests if a duplicate ID can be added.
     */
    public function testAddStreamDuplicateId()
    {
        $reader = new SocketReader();
        $stream = $this->createStream();
        $id = $reader->addStream($stream, "stream1");
        $stream2 = $this->createStream();
        $id2 = $reader->addStream($stream2, "stream1");

        self::assertNotEquals($id, $id2, 'A different ID returned.');
    }

    /**
     * Tests if the stream ID is returned when searching for it.
     */
    public function testAddStream_getId()
    {
        $reader = new SocketReader();
        $stream = $this->createStream();
        $id = $reader->addStream($stream, "stream1");

        $foundId = $reader->getId($stream);

        self::assertEquals($id, $foundId, 'Correct stream returned');
    }

    /**
     * Tests if a stream can't be accessed if it's removed.
     */
    public function testRemoveStream()
    {
        $reader = new SocketReader();
        $stream = $this->createStream();
        $id = $reader->addStream($stream, "stream1");

        $reader->removeStream($id);

        $foundId = $reader->getId($stream) !== FALSE;
        $foundStream = $reader->getStream($id);

        self::assertFalse($foundId, 'Stream not found');
        self::assertNull($foundStream, 'ID not found');
    }
}
