<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use RuntimeException;
use StephanSchuler\ForkJobRunner\Response\Response;
use StephanSchuler\ForkJobRunner\Response\Responses;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use function assert;
use function fclose;
use function fopen;
use function fputs;
use function getenv;
use function is_array;
use function is_resource;
use function posix_mkfifo;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class Dispatcher
{
    const FORK_QUEUE_COMMAND_CHANNEL = 'FORK_QUEUE_COMMAND_CHANNEL';

    /** @var string */
    private $loopCommand;

    /** @var ?resource */
    private $loopProcess;

    /** @var ?resource */
    private $commandChannel;

    /** @var ?string */
    private $commandChannelFileName;

    /** @var ?resource */
    private $stdOutChannel;

    /** @var ?resource */
    private $stdErrChannel;

    public function __construct(string $loopCommand)
    {
        $this->loopCommand = $loopCommand;
    }

    public function __destruct()
    {
        $this->closeLoop();
    }

    /**
     * @param Job $job
     * @return Responses<Response>
     */
    public function run(Job $job): Responses
    {
        $this->ensureLoop();

        $returnChannelPath = $job->getReturnChannel();

        $blockReturnChannel = fopen($returnChannelPath, 'ab+');
        if (!$blockReturnChannel) {
            throw new RuntimeException('Return channel cannot be blocked', 1621281816);
        }

        $returnChannel = fopen($returnChannelPath, 'rb');
        if (!$returnChannel) {
            throw new RuntimeException('Return channel unavailable', 1620514171);
        }

        $this->checkForRunningLoop();

        assert(is_resource($this->commandChannel));
        $put = fputs($this->commandChannel, PackageSerializer::toString($job));
        if ($put === false) {
            throw new RuntimeException('Command channel unavailable', 1620514181);
        }

        return Responses::create(
            $this,
            $returnChannelPath,
            $returnChannel,
            $blockReturnChannel
        );
    }

    public function checkForRunningLoop(): void
    {
        if (!$this->commandChannel || !is_resource($this->commandChannel)) {
            throw new RuntimeException('Command channel unavailable', 1620514175);
        }
        if (!$this->loopProcess || !is_resource($this->loopProcess)) {
            throw new RuntimeException('Loop process unavailable', 1620514177);
        }
        $status = proc_get_status($this->loopProcess);
        if (!is_array($status) || !$status['running']) {
            throw new RuntimeException('Loop process unavailable', 1620514178);
        }
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

    private function ensureLoop(): void
    {
        if (self::isRunnung($this->loopProcess)) {
            return;
        }

        $command = $this->loopCommand;

        $this->commandChannelFileName = (string)tempnam(sys_get_temp_dir(), 'command-channel');
        @unlink($this->commandChannelFileName);
        posix_mkfifo($this->commandChannelFileName, 0600);

        $environment = getenv();
        $environment[self::FORK_QUEUE_COMMAND_CHANNEL] = $this->commandChannelFileName;

        $descriptors = [
            ['pipe', 'rb'],
            ['file', '/dev/null', 'ab'],
            ['file', '/dev/null', 'ab'],
        ];

        $this->loopProcess = proc_open(
            $command,
            $descriptors,
            $pipes,
            null,
            $environment
        ) ?: null;

        $this->commandChannel = fopen($this->commandChannelFileName, 'wb') ?: null;
    }

    private function closeLoop(): void
    {
        if ($this->loopProcess) {
            @proc_terminate($this->loopProcess);
            $this->loopProcess = null;
        }
        if (is_resource($this->commandChannel)) {
            @fclose($this->commandChannel);
            $this->commandChannel = null;
        }
        if (is_string($this->commandChannelFileName)) {
            @unlink($this->commandChannelFileName);
            $this->commandChannelFileName = null;
        }
    }
}
