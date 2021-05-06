<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use Traversable;

class Dispatcher
{
    /**
     * @param Job $job
     * @return Traversable<string>
     */
    public function run(Job $job): Traversable
    {
        /** @var string[] $results */
        $results = [];

        $writeBack = function (string $line) use (&$results): void {
            $results[] = $line;
        };
        $job->run($writeBack);
        yield from $results;
    }
}