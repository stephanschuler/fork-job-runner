<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\BackChannel;

use StephanSchuler\ForkJobRunner\Response\Response;
use StephanSchuler\ForkJobRunner\Socket\WriteSocket as Socket;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;

final class WriteChannel extends BackChannel
{
    /** @var Socket */
    private $socket;

    /** @param Socket $socket */
    protected function __construct($socket)
    {
        $this->socket = $socket;
    }

    public function respond(Response $result): void
    {
        $this->socket->write(
            PackageSerializer::toString($result)
        );
    }
}
