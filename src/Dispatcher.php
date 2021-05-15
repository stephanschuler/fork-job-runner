<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use RuntimeException;
use StephanSchuler\ForkJobRunner\Response\Response;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use Traversable;
use function assert;
use function fclose;
use function feof;
use function fgets;
use function file_exists;
use function fopen;
use function fputs;
use function getenv;
use function is_array;
use function is_resource;
use function posix_mkfifo;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class Dispatcher
{
    /** @var string */
    private $loopCommand;

    /** @var ?resource */
    private $loopProcess;

    /** @var ?resource */
    private $commandChannel;

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
     * @return Traversable<Response>
     */
    public function run(Job $job): Traversable
    {
        $this->ensureLoop();

        $blockReturnChannel = fopen($job->getReturnChannel(), 'ab+');
        $returnChannel = fopen($job->getReturnChannel(), 'rb');
        if (!$returnChannel) {
            throw new RuntimeException('Return channel unavailable', 1620514171);
        }

        $this->checkForRunningLoop();

        assert(is_resource($this->commandChannel));
        $put = fputs($this->commandChannel, PackageSerializer::toString($job));
        if ($put === false) {
            throw new RuntimeException('Command channel unavailable', 1620514181);
        }

        while (true) {
            $read = [$returnChannel];
            $write = null;
            $except = null;

            stream_select($read, $write, $except, 1);

            if (isset($blockReturnChannel) && is_resource($blockReturnChannel)) {
                fclose($blockReturnChannel);
                unset($blockReturnChannel);
            }

            foreach ($read as $channel) {
                $content = fgets($channel);
                if ($content) {
                    yield PackageSerializer::fromString($content);
                }
            }

            $this->checkForRunningLoop();

            if (feof($returnChannel)) {
                break;
            }
        }

        fclose($returnChannel);
        @unlink($job->getReturnChannel());
    }

    protected function checkForRunningLoop(): void
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

        $descriptors = [
            ['pipe', 'rb'],
            ['file', '/dev/null', 'ab'],
            ['file', '/dev/null', 'ab'],
        ];

        $environment = getenv();

        $this->loopProcess = proc_open(
            $command,
            $descriptors,
            $pipes,
            null,
            $environment
        ) ?: null;

        $this->commandChannel = $pipes[0];
    }

    private function closeLoop(): void
    {
        if ($this->loopProcess) {
            proc_close($this->loopProcess);
            $this->loopProcess = null;
        }
    }
}
