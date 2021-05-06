<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

interface Job
{
    /**
     * @param callable(string $line):void $writeBack
     */
    public function run(callable $writeBack): void;
}