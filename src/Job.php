<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use StephanSchuler\ForkJobRunner\Utility\WriteBack;

interface Job
{
    public function run(WriteBack $writeBack): void;
}