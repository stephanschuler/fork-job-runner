<?php

namespace StephanSchuler\ForkJobRunner\Tests\Fixtures;

use RuntimeException;
use StephanSchuler\ForkJobRunner\Job;
use StephanSchuler\ForkJobRunner\Utility\WriteBack;

class ThrowableResponseJob implements Job
{
    /** @var string */
    private $returnChannelPath;

    public function __construct()
    {
        $this->returnChannelPath = (string)tempnam(sys_get_temp_dir(), 'return-channel');
        unlink(@$this->returnChannelPath);
        posix_mkfifo($this->returnChannelPath, 0600);
    }

    public function run(WriteBack $writeBack): void
    {
        throw new RuntimeException('Something went wrong!');
    }

    public function getReturnChannel(): string
    {
        return $this->returnChannelPath;
    }
}
