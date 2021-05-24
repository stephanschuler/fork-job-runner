<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Dispatcher;

final class FollowingDispatcher extends \StephanSchuler\ForkJobRunner\Dispatcher
{
    protected function __construct(string $socketFileName)
    {
        $this->socketFileName = $socketFileName;
    }
}
