<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use RuntimeException;
use StephanSchuler\ForkJobRunner\Response\Response;
use StephanSchuler\ForkJobRunner\Response\ThrowableResponse;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use Traversable;
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
    const RETURN_CHANNEL = 'RETURN_CHANNEL';

    /** @var string */
    private $loopPath;

    /** @var string */
    private $returnChannelPath;

    /** @var ?resource */
    private $loopProcess;

    /** @var ?resource */
    private $commandChannel;

    /** @var ?resource */
    private $stdOutChannel;

    /** @var ?resource */
    private $stdErrChannel;

    public function __construct(string $loopPath)
    {
        $this->loopPath = $loopPath;

        $this->returnChannelPath = (string)tempnam(sys_get_temp_dir(), 'return-channel');
        unlink(@$this->returnChannelPath);
        posix_mkfifo($this->returnChannelPath, 0600);
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

        $blockReturnChannel = fopen($this->returnChannelPath, 'ab+');
        $returnChannel = fopen($this->returnChannelPath, 'rb');
        if (!$returnChannel) {
            throw new RuntimeException('Return channel unavailable');
        }

        if (!$this->commandChannel) {
            throw new RuntimeException('Command channel unavailable');
        }
        $put = fputs($this->commandChannel, PackageSerializer::toString($job));
        if ($put === false) {
            yield new ThrowableResponse(
                new RuntimeException('Command channel unavailable')
            );
            return;
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

            if (feof($returnChannel)) {
                break;
            }
        }

        fclose($returnChannel);
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

        $command = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($this->loopPath);

        $descriptors = [
            ['pipe', 'rb'],
            ['file', '/dev/null', 'ab'],
            ['file', '/dev/null', 'ab'],
        ];

        $environment = getenv();
        $environment[self::RETURN_CHANNEL] = $this->returnChannelPath;

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
        if (file_exists($this->returnChannelPath)) {
            unlink($this->returnChannelPath);
        }
    }
}
