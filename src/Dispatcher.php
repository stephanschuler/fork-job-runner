<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use RuntimeException;
use StephanSchuler\ForkJobRunner\Response\Response;
use StephanSchuler\ForkJobRunner\Socket\Reader;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use Traversable;
use function escapeshellarg;
use function escapeshellcmd;
use function get_class;
use function gettype;
use function is_object;
use function is_resource;
use function posix_mkfifo;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use const PHP_BINARY;

class Dispatcher
{
    const RETURN_CHANNEL = 'RETURN_CHANNEL';

    /** @var string */
    private $loopPath;

    /** @var ?resource */
    private $loopProcess;

    /** @var bool */
    private $connectDefaultOutput;

    /** @var string */
    private $returnChannelPath;

    /** @var ?resource */
    private $commandChannel;

    public function __construct(string $loopPath, bool $connectDefaultOutput = false)
    {
        $this->loopPath = $loopPath;
        $this->connectDefaultOutput = $connectDefaultOutput;

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
        fputs($this->commandChannel, PackageSerializer::toString($job));

        $subjects = PackageSerializer::fromTraversable(
            new Reader($returnChannel)
        );

        foreach ($subjects as $subject) {
            if ($blockReturnChannel) {
                fclose($blockReturnChannel);
                $blockReturnChannel = null;
            }

            switch (true) {
                case !($subject instanceof Response):
                    $resultType = is_object($subject) ? get_class($subject) : gettype($subject);
                    throw new RuntimeException(
                        sprintf(
                            'Return channels must only transport Result objects, %s given.',
                            $resultType
                        )
                    );
                case $subject instanceof Response:
                    yield $subject;
            }
        }
    }

    private function ensureLoop(): void
    {
        if (is_resource($this->loopProcess) &&
            proc_get_status($this->loopProcess)['running'] === true &&
            proc_get_status($this->loopProcess)['exitcode'] === -1
        ) {
            return;
        }

        $command = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($this->loopPath);

        $descriptors = [
            ['pipe', 'rb'],
        ];
        if (!$this->connectDefaultOutput) {
            $descriptors[1] = ['file', '/dev/null', 'ab'];
            $descriptors[2] = ['file', '/dev/null', 'ab'];
        }

        $this->loopProcess = proc_open(
            $command,
            $descriptors,
            $pipes,
            null,
            [self::RETURN_CHANNEL => $this->returnChannelPath]
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
