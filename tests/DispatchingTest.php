<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Tests;

use PHPUnit\Framework\TestCase;
use StephanSchuler\ForkJobRunner\Dispatcher;
use StephanSchuler\ForkJobRunner\Job;

class DispatchingTest extends TestCase
{
    /**
     * @test
     */
    public function Dispatcher_call_jobs_and_return_data(): void
    {
        $job = new class implements Job {
            /** @inheritDoc */
            public function run(callable $writeBack): void
            {
                $writeBack('first line');
                $writeBack('second line');
            }
        };

        $dispatcher = new Dispatcher();
        $result = $dispatcher->run($job);

        self::assertInstanceOf(\Generator::class, $result);
        self::assertEquals(['first line', 'second line'], iterator_to_array($result));
    }

}