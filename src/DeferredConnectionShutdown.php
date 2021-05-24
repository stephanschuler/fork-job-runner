<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use function fclose;
use function is_null;
use function stream_socket_shutdown;

final class DeferredConnectionShutdown
{
    /** @var ?resource */
    private $connection;

    /** @param resource $connection */
    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function __invoke(): bool
    {
        return $this->close();
    }

    public function close(): bool
    {
        if (is_null($this->connection)) {
            return false;
        }

        stream_socket_shutdown($this->connection, \STREAM_SHUT_RDWR);
        fclose($this->connection);
        $this->connection = null;
        return true;
    }
}
