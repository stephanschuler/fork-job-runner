<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use RuntimeException;
use StephanSchuler\ForkJobRunner\Dispatcher\FollowingDispatcher;
use StephanSchuler\ForkJobRunner\Dispatcher\LeadingDispatcher;
use StephanSchuler\ForkJobRunner\Response\Response;
use StephanSchuler\ForkJobRunner\Response\Responses;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use function fopen;
use function fputs;
use function getenv;

abstract class Dispatcher
{
    const FORK_QUEUE_COMMAND_CHANNEL = 'FORK_QUEUE_COMMAND_CHANNEL';

    abstract protected function __construct(string $_);

    public static function create(string $loopCommand): self
    {
        $commandChannelName = (string)getenv(self::FORK_QUEUE_COMMAND_CHANNEL);
        if ($commandChannelName === '') {
            return new LeadingDispatcher($loopCommand);
        } else {
            return new FollowingDispatcher($commandChannelName);
        }
    }

    /**
     * @param Job $job
     * @return Responses<Response>
     */
    public function run(Job $job): Responses
    {
        $this->ensureLoop();
        $commandChannel = $this->getCommandChannel();

        $returnChannelPath = $job->getReturnChannel();

        $blockReturnChannel = fopen($returnChannelPath, 'ab+');
        if (!$blockReturnChannel) {
            throw new RuntimeException('Return channel cannot be blocked', 1621281816);
        }

        $returnChannel = fopen($returnChannelPath, 'rb');
        if (!$returnChannel) {
            throw new RuntimeException('Return channel unavailable', 1620514171);
        }

        $this->checkForRunningLoop();

        $put = fputs($commandChannel, PackageSerializer::toString($job));
        if ($put === false) {
            throw new RuntimeException('Command channel unavailable', 1620514181);
        }

        return Responses::create(
            $this,
            $returnChannelPath,
            $returnChannel,
            $blockReturnChannel
        );
    }

    abstract public function checkForRunningLoop(): void;

    abstract protected function ensureLoop(): void;

    /** @var resource */
    abstract protected function getCommandChannel();
}
