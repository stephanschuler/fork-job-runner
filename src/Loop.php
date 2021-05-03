<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use RuntimeException;
use StephanSchuler\ForkJobRunner\Response\NoOpResponse;
use StephanSchuler\ForkJobRunner\Response\ThrowableResponse;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use Throwable;
use function assert;
use function fclose;
use function fgets;
use function fopen;
use function getenv;
use function pcntl_fork;
use function set_exception_handler;
use function trim;
use const STDIN;

class Loop
{
    /** @var string */
    private $returnChannelPath;

    public function __construct()
    {
        $this->returnChannelPath = getenv(Dispatcher::RETURN_CHANNEL) ?: '/tmp/return-channel';
    }


    /**
     * @return never-returns
     * @throws Throwable
     */
    public function run(): void
    {
        while ($data = trim((string)fgets(STDIN), PackageSerializer::SPLITTER)) {
            list($read, $write) = BackChannel\BackChannel::create();
            $child = pcntl_fork();

            if ($child === -1) {
                throw new RuntimeException('Could not fork into isolated execution');
            } elseif ($child === 0) {
                unset($read);
                $this->asChild($write, $data);
            } else {
                unset($write);
                $this->asParent($read);
            }
        }

        exit;
    }

    /**
     * @param BackChannel\WriteChannel $backChannel
     * @param string $data
     * @throws Throwable
     * @return never-returns
     */
    final protected function asChild(BackChannel\WriteChannel $backChannel, string $data): void
    {
        set_exception_handler(function (Throwable $throwable) use ($backChannel) {
            $backChannel->respond(
                new ThrowableResponse($throwable)
            );
            throw $throwable;
        });

        $backChannel->respond(new NoOpResponse());

        $job = PackageSerializer::fromString($data);
        assert($job instanceof Job);
        $job->run($backChannel);

        exit;
    }

    final protected function asParent(BackChannel\ReadChannel $backChannel): void
    {
        $returnChannel = fopen($this->returnChannelPath, 'ab+');
        if (!$returnChannel) {
            throw new RuntimeException('Could not open return channel');
        }

        $backChannel->copyTo($returnChannel);

        fclose($returnChannel);
    }
}
