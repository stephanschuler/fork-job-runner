<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use StephanSchuler\ForkJobRunner\BackChannel;

interface Job
{
    public function run(BackChannel\WriteChannel $writer): void;
}
