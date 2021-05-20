<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Dispatcher;

use function fopen;
use function is_resource;

final class FollowingDispatcher extends \StephanSchuler\ForkJobRunner\Dispatcher
{
    /** @var string */
    private $commandChannelFileName;

    /** @var ?resource */
    private $commandChannel;

    protected function __construct(string $commandChannelFileName)
    {
        $this->commandChannelFileName = $commandChannelFileName;
    }

    public function checkForRunningLoop(): void
    {
        if (!$this->commandChannel || !is_resource($this->commandChannel)) {
            throw new RuntimeException('Command channel unavailable', 1621495701);
        }
    }

    protected function ensureLoop(): void
    {
        if (!$this->commandChannel || !is_resource($this->commandChannel)) {
            $this->commandChannel = fopen($this->commandChannelFileName, 'wb') ?: null;
        }
    }

    /** @var resource */
    protected function getCommandChannel()
    {
        assert(is_resource($this->commandChannel));
        return $this->commandChannel;
    }
}
