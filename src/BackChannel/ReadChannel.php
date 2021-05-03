<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\BackChannel;

use StephanSchuler\ForkJobRunner\Response;
use StephanSchuler\ForkJobRunner\Socket\ReadSocket as Socket;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use Traversable;

final class ReadChannel extends BackChannel
{
    /** @var Socket */
    private $socket;

    /** @param Socket $socket */
    protected function __construct($socket)
    {
        $this->socket = $socket;
    }

    /** @param resource $resource */
    public function copyTo($resource): void
    {
        stream_copy_to_stream($this->socket->export(), $resource);
    }
}
