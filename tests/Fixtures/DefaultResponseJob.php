<?php

namespace StephanSchuler\ForkJobRunner\Tests\Fixtures;

use StephanSchuler\ForkJobRunner\Job;
use StephanSchuler\ForkJobRunner\Response\DefaultResponse;
use StephanSchuler\ForkJobRunner\Utility\WriteBack;

class DefaultResponseJob implements Job
{
    /** @var string[] $messages */
    private $messages;

    /** @var string */
    private $returnChannelPath;

    public function __construct(string ...$messages)
    {
        $this->messages = $messages;

        $this->returnChannelPath = (string)tempnam(sys_get_temp_dir(), 'return-channel');
        unlink(@$this->returnChannelPath);
        posix_mkfifo($this->returnChannelPath, 0600);
    }

    public function run(WriteBack $writeBack): void
    {
        foreach ($this->messages as $message) {
            $writeBack->send(
                new DefaultResponse($message)
            );
        }
    }

    public function getReturnChannel(): string
    {
        return $this->returnChannelPath;
    }
}
