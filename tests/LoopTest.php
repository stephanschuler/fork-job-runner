<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Tests;

use PHPUnit\Framework\TestCase;
use StephanSchuler\ForkJobRunner\Loop;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use function fopen;
use function fputs;
use function rewind;

class LoopTest extends TestCase
{
    /**
     * @test
     */
    public function Loop_calls_jobs_from_command_stream(): void
    {
        $job = new TestJob('line 1', 'line 2');

        /** @var resource $stream */
        $stream = fopen('php://temp', 'w+');
        fputs($stream, PackageSerializer::toString($job));

        rewind($stream);

        $loop = new Loop($stream);

        self::expectOutputString('line 1' . PHP_EOL . 'line 2' . PHP_EOL);
        $loop->run();
    }
}