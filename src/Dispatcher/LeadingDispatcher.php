<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Dispatcher;

use function file_exists;
use function getenv;
use function is_array;
use function is_resource;
use function is_string;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class LeadingDispatcher extends \StephanSchuler\ForkJobRunner\Dispatcher
{
    /** @var string */
    private $loopCommand;

    /** @var ?resource */
    private $loopProcess;

    protected function __construct(string $loopCommand)
    {
        $this->loopCommand = $loopCommand;
    }

    public function __destruct()
    {
        $this->closeLoop();
    }

    protected function ensureLoop(): void
    {
        if (self::isRunnung($this->loopProcess)) {
            return;
        }

        $command = $this->loopCommand;

        $socketFileName = (string)tempnam(sys_get_temp_dir(), 'fork-queue-');
        unlink($socketFileName);
        $this->socketFileName = $socketFileName;

        $environment = getenv();
        $environment[self::FORK_QUEUE_COMMAND_CHANNEL] = $this->socketFileName;

        $descriptors = [
            ['pipe', 'rb'],
            \STDOUT,
            \STDERR,
            ['pipe', 'w'],
        ];

        $this->loopProcess = proc_open(
            $command,
            $descriptors,
            $pipes,
            null,
            $environment
        ) ?: null;
    }

    /** @param resource|null $process */
    private static function isRunnung($process): bool
    {
        if (!is_resource($process)) {
            return false;
        }
        $status = proc_get_status($process);
        if (!is_array($status)) {
            return false;
        }
        if ($status['running'] === true && $status['exitcode'] === -1) {
            return true;
        }
        return false;
    }

    private function closeLoop(): void
    {
        if (is_resource($this->loopProcess)) {
            proc_terminate($this->loopProcess);
            $this->loopProcess = null;
        }
        if (is_string($this->socketFileName) && file_exists($this->socketFileName)) {
            unlink($this->socketFileName);
            $this->socketFileName = null;
        }
    }
}
