<?php

namespace WebSockets\Common;

/**
 * A WebSocket Frame.
 *
 * Assumes running in the context of a server.
 *
 * @package WebSockets\Server
 */
class Frame
{
    /** @var bool TRUE if it's the final fragment. */
    private $final = FALSE;
    /** @var int 4-bit opcode */
    private $opcode = self::OPCODE_TEXT;
    /** @var bool TRUE if payload is masked */
    private $masked = FALSE;
    /** @var int Payload length */
    private $length;
    /** @var string Masking-key */
    private $maskKey;
    /** @var string The (possibly incomplete) data buffer for the frame. */
    private $data;
    /** @var string The payload. */
    private $payload;
    /** @var int Where in the data buffer the payload starts. */
    private $payloadOffset;
    /** @var bool TRUE if the header has been parsed. */
    private $headerParsed = FALSE;
    /** @var bool TRUE if all the data has been received. */
    private $complete = FALSE;
    /** @var bool TRUE if the frame is from the client. */
    private $fromClient = TRUE;
    /** @var string Any data after the frame (could be for the next frame) */
    private $extraData;

    const OPCODE_TEXT = 0x01;
    const OPCODE_BINARY = 0x02;

    const OPCODE_CLOSE = 0x08;
    const OPCODE_PING = 0x09;
    const OPCODE_PONG = 0x0A;


    /**
     * @param $fromClient TRUE if the frame was from the client.
     */
    public function __construct($fromClient)
    {
        $this->fromClient = $fromClient;
    }

    /**
     * Creates a Frame (or array of frames if one frame isn't enough) that's to be sent to a client.
     *
     * @param $opcode
     * @param $payload
     * @return Frame|Frame[]
     */
    public static function ForClient($opcode, $payload)
    {
        $frame = new Frame(FALSE);
        $frame->opcode = (int)$opcode;
        $frame->payload = $payload;
        $frame->length = strlen($frame->payload);
        return $frame;
    }

    /**
     * Creates a text Frame for the client for the given message.
     *
     * @param $message string The message.
     * @param bool $binary TRUE for binary message, otherwise a text message.
     * @return Frame|Frame[]
     */
    public static function FromMessage($message, $binary = FALSE)
    {
        return self::ForClient($binary ? self::OPCODE_BINARY : self::OPCODE_TEXT, $message);
    }

    /**
     * Gets the data.
     *
     * @return string
     */
    public function getData()
    {
        if (!$this->fromClient && $this->data === NULL) {
            $this->data = $this->generateData();
        }

        return $this->data;
    }

    /**
     * Generates the frame data.
     *
     * @return string The frame to send to the client.
     */
    private function generateData()
    {
        /* https://tools.ietf.org/html/rfc6455#section-5.2
          0                   1                   2                   3
          0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
         +-+-+-+-+-------+-+-------------+-------------------------------+
         |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
         |I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
         |N|V|V|V|       |S|             |   (if payload len==126/127)   |
         | |1|2|3|       |K|             |                               |
         +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
         |     Extended payload length continued, if payload len == 127  |
         + - - - - - - - - - - - - - - - +-------------------------------+
         |                               |Masking-key, if MASK set to 1  |
         +-------------------------------+-------------------------------+
         | Masking-key (continued)       |          Payload Data         |
         +-------------------------------- - - - - - - - - - - - - - - - +
         :                     Payload Data continued ...                :
         + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
         |                     Payload Data continued ...                |
         +---------------------------------------------------------------+
         */


        $data = '';

        // bit 0: FIN (1)
        // bits 4-7: opcode
        $data .= chr(0x80 | ($this->opcode & 0x0F));

        if ($this->length < 126) {
            $len = $this->length;
            $lenBytes = NULL;
        } else {
            $short = ($this->length < 0xFFFF);

            if ($short) {
                $len = 126;
                $lenBytes = pack("n", $this->length);
            } else {
                $len = 127;
                $hi = ($this->length & 0xFFFFFFFF00000000) >> 32;
                $lo = ($this->length & 0xFFFFFFFF);
                $lenBytes = pack("NN", $hi, $lo);
            }
        }

        // bit 8: MASK (0)
        // bits 9-15: Payload len
        $data .= chr($len);

        if ($lenBytes !== NULL) {
            // bits 16+ Extended payload length
            $data .= $lenBytes;
        }

        $this->payloadOffset = strlen($data);
        $data .= $this->payload;

        return $data;
    }

    /**
     * Call when there's some data for the frame.
     *
     * @param $data string The data. This may be more or less data than what the frame requires.
     * @return bool FALSE if the full frame hasn't been received.
     * @throws BadFrameException thrown if there is something wrong with the frame.
     */
    public function gotData($data)
    {
        $this->data .= $data;
        $this->parseHeader();

        if (!$this->headerParsed) {
            return FALSE;
        }

        $expectedLength = $this->payloadOffset + $this->length;
        $actualLength = strlen($this->data);

        if ($actualLength < $expectedLength) {
            return FALSE;
        } else if ($actualLength > $expectedLength) {
            // received more data than required (possibly the next frame)
            $this->extraData = substr($this->data, $expectedLength);
            $this->data = substr($this->data, 0, $expectedLength);
        } else {
            $this->extraData = NULL;
        }

        $this->gotFullFrame();
        return TRUE;
    }

    /**
     * Parse the header.
     * @return bool TRUE if the header has been parsed. FALSE if there's not enough data.
     * @throws BadFrameException thrown if there is something wrong with the frame.
     */
    private function parseHeader()
    {
        if ($this->headerParsed) {
            return TRUE;
        }

        /* https://tools.ietf.org/html/rfc6455#section-5.2
          0                   1                   2                   3
          0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
         +-+-+-+-+-------+-+-------------+-------------------------------+
         |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
         |I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
         |N|V|V|V|       |S|             |   (if payload len==126/127)   |
         | |1|2|3|       |K|             |                               |
         +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
         |     Extended payload length continued, if payload len == 127  |
         + - - - - - - - - - - - - - - - +-------------------------------+
         |                               |Masking-key, if MASK set to 1  |
         +-------------------------------+-------------------------------+
         | Masking-key (continued)       |          Payload Data         |
         +-------------------------------- - - - - - - - - - - - - - - - +
         :                     Payload Data continued ...                :
         + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
         |                     Payload Data continued ...                |
         +---------------------------------------------------------------+
         */

        $dataLen = strlen($this->data);

        if ($dataLen < 2) {
            return FALSE;
        }

        $offset = 0;
        $head = [ord($this->data[$offset]), ord($this->data[$offset+1])];
        $offset += 2;

        // bit 0: FIN
        $this->final = (bool)($head[0] & 0x80);
        // bits 4-7: opcode
        $this->opcode = $head[0] & 0x0F;

        // bit 8: MASK
        $this->masked = (bool)($head[1] & 0x80);
        // bits 9-15: Payload len
        $this->length = $head[1] & 0x7F;

        // Get the extended length (if specified).
        if ($this->length >= 0x7E) {
            $shortLen = $this->length === 0x7E;
            if ($shortLen) {
                // 16 bit length
                $unpackFormat = "n1len";
                $lenSize = 2;
            } else {
                // 64 bit length (parsed as 2x32bit)
                if (PHP_INT_SIZE < 8) {
                    throw new BadFrameException("64-bit frame length not supported on a 32-bit system.");
                }

                $unpackFormat = "N2len";
                $lenSize = 8;
            }

            // Not enough data yet.
            if ($dataLen < $offset + $lenSize) {
                return FALSE;
            }

            $l = unpack($unpackFormat, substr($this->data, $offset, $lenSize));

            if ($shortLen) {
                $this->length = $l['len'];
            } else {
                $this->length = ((int)$l['len1'] << 32) | (int)$l['len2'];
            }

            $offset += $lenSize;
        }

        if ($this->isControl()) {
            // All control frames MUST have a payload length of 125 bytes or less
            // and MUST NOT be fragmented.
            if ($this->length > 125) {
                throw new BadFrameException("Control frame too long.");
            } else if (!$this->final) {
                throw new BadFrameException("Control frame is fragmented.");
            }
        }

        if ($this->masked) {
            if ($dataLen < $offset + 4) {
                return FALSE;
            }

            $this->maskKey = substr($this->data, $offset, 4);
            $offset += 4;
        }

        if (!$this->masked && $this->fromClient) {
            throw new BadFrameException("Frames from the client must be masked.");
        }

        $this->payloadOffset = $offset;
        $this->headerParsed = TRUE;

        return TRUE;
    }

    private function gotFullFrame()
    {
        $this->payload = substr($this->data, $this->payloadOffset);
        unset($this->data);

        if ($this->masked) {
            $this->mask();
            $this->masked = FALSE;
        }
        $this->complete = TRUE;
    }

    /**
     * Mask/unmask the data.
     */
    private function mask()
    {
        // XOR of the payload and mask key
        for ($n = 0; $n < $this->length; $n++) {
            /** @noinspection OpAssignShortSyntaxInspection */
            $this->payload[$n] = $this->payload[$n] ^ $this->maskKey[$n % 4];
        }
    }



    /**
     * Returns TRUE if the full frame has been received.
     *
     * @return boolean
     */
    public function isComplete()
    {
        return $this->complete;
    }

    /**
     * Returns TRUE if the header has been parsed.
     *
     * @return boolean
     */
    public function isHeaderParsed()
    {
        return $this->headerParsed;
    }

    /**
     * Returns TRUE if this frame is the final frame for the message.
     *
     * @return boolean
     */
    public function isFinal()
    {
        return $this->final;
    }

    /**
     * Gets the opcode.
     *
     * @return int
     */
    public function getOpcode()
    {
        return $this->opcode;
    }

    /**
     * Gets the payload.
     *
     * @return string
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * Returns TRUE if the frame was from the client. Otherwise, it's one to be sent to the client.
     *
     * @return boolean
     */
    public function isFromClient()
    {
        return $this->fromClient;
    }

    /**
     * Returns TRUE if this is a data frame. FALSE for a control frame.
     *
     * @return int
     */
    public function isData()
    {
        return $this->opcode & 0x0 === 0x00;
    }

    /**
     * Returns TRUE if this is a control frame. FALSE for a data frame.
     *
     * @return bool
     */
    public function isControl()
    {
        return !$this->isData();
    }

    /**
     * Gets any extra data after this frame.
     *
     * @return string|NULL
     */
    public function getExtraData()
    {
        return $this->extraData;
    }


}