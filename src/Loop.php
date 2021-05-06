<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use function assert;
use function fgets;
use function trim;
use const PHP_EOL;

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
        while ($data = trim((string)fgets($this->commandChannel), PHP_EOL)) {
            $job = PackageSerializer::fromString($data);
            assert($job instanceof Job);
            $writer = function (string $line): void {
                echo $line . PHP_EOL;
            };
            $job->run($writer);
        }
    }
}