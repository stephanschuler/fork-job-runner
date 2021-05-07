<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use RuntimeException;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use Throwable;
use function assert;
use function fgets;
use function pcntl_fork;
use function pcntl_waitpid;
use function set_exception_handler;
use function trim;

class Loop
{
    /**
     * @var resource
     */
    private $commandChannel;

    /**
     * @param resource $commandChannel
     */
    public function __construct($commandChannel)
    {
        $this->commandChannel = $commandChannel;
    }

    public function run(): void
    {
        while ($data = trim((string)fgets($this->commandChannel), PackageSerializer::SPLITTER)) {
            $child = pcntl_fork();

            if ($child === -1) {
                throw new RuntimeException('Could not fork into isolated execution');
            } elseif ($child === 0) {
                $this->asChild($data);
            } else {
                $this->asParent($child);
            }
        }

        exit;
    }

    final protected function asChild(string $data): void
    {
        set_exception_handler(static function (Throwable $throwable): void {
            throw $throwable;
        });

        $job = PackageSerializer::fromString($data);
        assert($job instanceof Job);
        $job->run(function (string $line) {
            echo $line . PHP_EOL;
        });

        exit;
    }

    final protected function asParent(int $child): void
    {
        pcntl_waitpid($child, $statuscode);
    }
}