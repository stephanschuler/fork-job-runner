<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Socket;

use RuntimeException;

final class ReadSocket extends Socket
{
    /** @return resource */
    public function export()
    {
        if (!is_resource($this->resources->read)) {
            throw new RuntimeException('Resource object not available');
        }
        return $this->resources->read;
    }
}
