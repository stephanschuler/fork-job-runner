<?php

namespace StephanSchuler\ForkJobRunner\Tests\Fixtures;

use StephanSchuler\ForkJobRunner\Job;
use StephanSchuler\ForkJobRunner\Response\DefaultResponse;
use StephanSchuler\ForkJobRunner\Utility\WriteBack;

class DefaultResponseJob implements Job
{
    /** @var string[] $messages */
    private $messages;

    public function __construct(string ...$messages)
    {
        $this->messages = $messages;
    }

    public function run(WriteBack $writeBack): void
    {
        foreach ($this->messages as $message) {
            $writeBack->send(
                new DefaultResponse($message)
            );
        }
    }
}
