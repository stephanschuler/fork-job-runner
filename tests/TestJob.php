<?php

namespace StephanSchuler\ForkJobRunner\Tests;

use StephanSchuler\ForkJobRunner\Job;
use StephanSchuler\ForkJobRunner\Response\DefaultResponse;


class TestJob implements Job
{
    /** @var string[] $messages */
    private $messages;

    public function __construct(string ...$messages)
    {
        $this->messages = $messages;
    }

    public function run(callable $writeBack): void
    {
        foreach ($this->messages as $message) {
            $writeBack(
                new DefaultResponse($message)
            );
        }
    }
}