<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Socket;

use function stream_socket_shutdown;
use const STREAM_SHUT_RDWR;

/**
 * @internal
 */
class Resources
{
    /** @var ?resource */
    public $read;

    /** @var ?resource */
    public $write;

    /**
     * @param resource $read
     * @param resource $write
     */
    public function __construct($read, $write)
    {
        $this->read = $read;
        $this->write = $write;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if ($this->write) {
            @stream_socket_shutdown($this->write, STREAM_SHUT_RDWR);
            $this->write = null;
        }
        if ($this->read) {
            @stream_socket_shutdown($this->read, STREAM_SHUT_RDWR);
            $this->read = null;
        }
    }
}
