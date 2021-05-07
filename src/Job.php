<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use StephanSchuler\ForkJobRunner\Response\Response;

interface Job
{
    /**
     * @param callable(Response $line):void $writeBack
     * @see Response
     */
    public function run(callable $writeBack): void;
}