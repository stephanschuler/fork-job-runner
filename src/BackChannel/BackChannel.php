<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\BackChannel;

use StephanSchuler\ForkJobRunner\Socket;

abstract class BackChannel
{
    /** @return array{0: ReadChannel, 1: WriteChannel} */
    public static function create(): array
    {
        list($readSocket, $writeSocket) = Socket\Socket::create();
        $read = new ReadChannel($readSocket);
        $write = new WriteChannel($writeSocket);
        return [$read, $write];
    }
}
