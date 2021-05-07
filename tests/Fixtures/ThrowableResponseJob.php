<?php

namespace StephanSchuler\ForkJobRunner\Tests\Fixtures;

use RuntimeException;
use StephanSchuler\ForkJobRunner\Job;
use StephanSchuler\ForkJobRunner\Utility\WriteBack;

class ThrowableResponseJob implements Job
{
    public function run(WriteBack $writeBack): void
    {
        throw new RuntimeException('Something went wrong!');
    }
}