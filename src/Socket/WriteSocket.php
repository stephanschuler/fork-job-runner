<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Socket;

use RuntimeException;
use function fputs;

final class WriteSocket extends Socket
{
    public function write(string $data): void
    {
        if (!$this->resources->write) {
            throw new RuntimeException('Connection is closed.');
        }
        fputs($this->resources->write, $data);
    }
}
