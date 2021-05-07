<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use RuntimeException;
use StephanSchuler\ForkJobRunner\Response\NoOpResponse;
use StephanSchuler\ForkJobRunner\Response\ThrowableResponse;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use StephanSchuler\ForkJobRunner\Utility\WriteBack;
use function assert;
use function fclose;
use function fgets;
use function fputs;
use function pcntl_fork;
use function pcntl_waitpid;
use function trim;

class Loop
{
    /** @var string */
    private $commandChannel;

    /** @var string */
    private $returnChannel;

    public function __construct(string $commandChannel, string $returnChannel)
    {
        $this->commandChannel = $commandChannel;
        $this->returnChannel = $returnChannel;
    }

    public function run(): void
    {
        $commandChannel = fopen($this->commandChannel, 'rb');
        if (!$commandChannel) {
            throw new \RuntimeException('Could not open command channel');
        }

        while ($data = trim((string)fgets($commandChannel), PackageSerializer::SPLITTER)) {
            $child = pcntl_fork();

            if ($child === -1) {
                throw new RuntimeException('Could not fork into isolated execution');
            } elseif ($child === 0) {
                $this->asChild($data);
                // Children exit after doing the work
                exit;
            } else {
                $this->asParent($child);
                // Parents continue to loop
            }
        }

        fclose($commandChannel);
        exit;
    }

    final protected function asChild(string $data): void
    {
        $job = PackageSerializer::fromString($data);
        assert($job instanceof Job);

        $returnChannel = fopen($this->returnChannel, 'wb+');
        if (!$returnChannel) {
            throw new \RuntimeException('Could not open return channel');
        }

        fputs($returnChannel, PackageSerializer::toString(new NoOpResponse()));
        try {
            $writeBack = new WriteBack($returnChannel);
            $job->run($writeBack);
        } catch (\Exception $throwable) {
            fputs($returnChannel, PackageSerializer::toString(new ThrowableResponse($throwable)));
            throw $throwable;
        } finally {
            fclose($returnChannel);
        }
    }

    final protected function asParent(int $child): void
    {
        pcntl_waitpid($child, $statuscode);
    }
}