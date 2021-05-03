<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Socket;

use RuntimeException;
use function is_array;
use function stream_socket_pair;
use const STREAM_IPPROTO_IP;
use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

abstract class Socket
{
    /** @var Resources */
    protected $resources;

    private function __construct(Resources $resources)
    {
        $this->resources = $resources;
    }

    /** @return array{0: ReadSocket, 1: WriteSocket} */
    public static function create(): array
    {
        $socket = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if (!is_array($socket)) {
            throw new RuntimeException('Could not initialize IO sockets');
        }
        $write = $socket[0];
        $read = $socket[1];

        $resources = new Resources($read, $write);

        return [
            new ReadSocket($resources),
            new WriteSocket($resources)
        ];
    }
}
