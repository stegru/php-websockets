<?php

namespace tests;


use WebSockets\Common\Frame;

/**
 * Tests for the FrameTest class.
 *
 * @package tests
 */
class FrameTest extends \PHPUnit_Framework_TestCase
{
    const HelloFrame = "8185360c1ade5e6976b259";

    private function createFrame($hex = NULL)
    {
        $frame = new Frame(TRUE);
        if (isset($hex)) {
            $frame->gotData(hex2bin($hex));
        }

        return $frame;
    }

    /**
     * Tests isComplete() returns TRUE on a complete frame.
     */
    public function testFullFrameComplete()
    {
        $frame = $this->createFrame(self::HelloFrame);

        self::assertTrue($frame->isComplete());
    }

    /**
     * Tests isComplete() returns FALSE on an incomplete frame.
     */
    public function testFullFrameNotComplete()
    {
        $frame = $this->createFrame(substr(self::HelloFrame, 0, -2));

        self::assertFalse($frame->isComplete());
    }


    /**
     * Tests if the payload is correct.
     */
    public function testFramePayload()
    {
        $frame = $this->createFrame(self::HelloFrame);

        self::assertEquals("hello", $frame->getPayload());
    }

    /**
     * Tests if a frame can be filled byte-by-byte.
     */
    public function testPartialFrame()
    {
        $frame = $this->createFrame();
        $data = hex2bin(self::HelloFrame);

        for ($n = 0; $n < strlen($data); $n++) {
            $frame->gotData($data[$n]);
        }

        self::assertTrue($frame->isComplete());
        self::assertEquals("hello", $frame->getPayload());
    }

    /**
     * Tests if a FIN flag of 1 is detected.
     */
    public function testFrameFinal()
    {
        $frame = $this->createFrame(self::HelloFrame);

        self::assertTrue($frame->isFinal());
    }

    /**
     * Tests if the opcode is correctly read.
     */
    public function testFrameOpcode()
    {
        $frame = $this->createFrame(self::HelloFrame);

        self::assertEquals(Frame::OPCODE_TEXT, $frame->getOpcode());
    }

    /**
     * Tests if no data added after the frame works.
     */
    public function testExtraDataNone()
    {
        $frame = $this->createFrame(self::HelloFrame);

        self::assertNull($frame->getExtraData());
    }

    /**
     * Tests if extra data added after the frame works.
     */
    public function testExtraDataGiven()
    {
        $extraData = "aabbccddee";
        $frame = $this->createFrame(self::HelloFrame . $extraData);

        self::assertEquals($extraData, bin2hex($frame->getExtraData()));
    }
}
