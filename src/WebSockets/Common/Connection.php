<?php
/**
 * Created by PhpStorm.
 * User: ste
 * Date: 18/08/15
 * Time: 20:04
 */

namespace WebSockets\Common;


/**
 * A connection to an end-point.
 *
 * @package WebSockets\Common
 */
abstract class Connection
{
    use MessageDispatcherTrait;

    /** @var resource */
    private $stream;

    /** @var Frame */
    private $currentFrame;

    /** @var bool TRUE if the connect has been closed. */
    private $closed = FALSE;

    /** @var string The current message. */
    private $message = "";

    private $id;


    /**
     * @param resource $stream
     * @param $id
     */
    public function __construct($stream, $id)
    {
        $this->stream = $stream;
        $this->id = $id;
    }

    /**
     * Called when some data has been received from the client.
     *
     * @param string $data The data received.
     */
    public function gotData($data)
    {
        if ($this->currentFrame === NULL) {
            $this->currentFrame = new Frame(TRUE);
        }

        try {
            $this->currentFrame->gotData($data);
        } catch (BadFrameException $ex) {
            $this->closeConnection();
        }

        if (!$this->currentFrame->isComplete()) {
            // The full frame hasn't been received.
            return;
        }

        $this->gotFrame($this->currentFrame);

        $extra = $this->currentFrame->getExtraData();
        $this->currentFrame = NULL;

        if ($extra !== NULL) {
            $this->gotData($extra);
        }
    }

    /**
     * Called when a full frame has been received.
     *
     * @param Frame $frame
     */
    private function gotFrame(Frame $frame)
    {
        if ($frame->isControl()) {
            switch ($frame->getOpcode()) {
                case Frame::OPCODE_CLOSE:
                    break;

                case Frame::OPCODE_PING:
                    //$this->sendFrame(new Frame());
                    break;

                case Frame::OPCODE_PONG:
                    break;

            }
        } else {

            $this->message .= $frame->getPayload();
            if ($frame->isFinal()) {
                $this->gotMessage();
                $this->message = NULL;
            }
        }
    }

    /**
     * Send some data to the remote end point.
     *
     * @param string $data The data to send.
     * @return int
     */
    public function sendData($data)
    {
        return fwrite($this->stream, $data);
    }

    /**
     * The client connection was closed.
     */
    public function connectionClosed()
    {
        $this->notifyMessageListeners($this, NULL);
    }

    /**
     * Returns TRUE if the connection is closed.
     *
     * @return bool
     */
    public function isClosed()
    {
        return $this->closed;
    }

    /**
     * Called when a complete message has been received.
     */
    private function gotMessage()
    {
        $this->notifyMessageListeners($this, $this->message);
    }

    /**
     * Send a message to the remote end point.
     *
     * @param $message
     * @param bool $binary TRUE for a binary message, FALSE for text message.
     */
    public function sendMessage($message, $binary = FALSE)
    {
        $this->sendData(Frame::FromMessage($message, $binary)->getData());
    }

    /**
     * Gets the identifier for this connection.
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets the current frame.
     *
     * @return Frame
     */
    public function getCurrentFrame()
    {
        return $this->currentFrame;
    }

    /**
     * Closes the connection.
     */
    private function closeConnection()
    {
        fclose($this->stream);
    }

}
