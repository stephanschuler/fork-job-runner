<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use Exception;
use RuntimeException;
use spec\Http\Client\Common\Plugin\ResponseSeekableBodyPluginSpec;
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

final class Loop
{
    /** @var string */
    private $commandChannel;

    /** @var string */
    private $returnChannel;

    /** @var resource[] */
    private static $returnChannels = [];

    public function __construct(string $commandChannel, string $returnChannel)
    {
        $this->commandChannel = $commandChannel;
        $this->returnChannel = $returnChannel;
    }

    public static function create(): self
    {
        return new static('php://stdin', 'php://stdout');
    }

    public function readFrom(string $commandChannel): self
    {
        return new static($commandChannel, $this->returnChannel);
    }

    public function writeTo(string $returnChannel): self
    {
        return new static($this->commandChannel, $returnChannel);
    }

    public function run(): void
    {
        $commandChannel = fopen($this->commandChannel, 'rb');
        if (!$commandChannel) {
            throw new RuntimeException('Could not open command channel', 1620514191);
        }

        while ($data = trim((string)fgets($commandChannel), PackageSerializer::SPLITTER)) {
            $child = pcntl_fork();

            if ($child === -1) {
                throw new RuntimeException('Could not fork into isolated execution', 1620514200);
            } elseif ($child === 0) {
                $this->asChild($data);
                // Children stop looping after doing the work
                return;
            } else {
                $this->asParent($child);
                // Parents continue to loop
            }
        }

        fclose($commandChannel);
    }

    final protected function asChild(string $data): void
    {
        $job = PackageSerializer::fromString($data);
        assert($job instanceof Job);

        $returnChannel = fopen($this->returnChannel, 'wb+');
        if (!$returnChannel) {
            throw new RuntimeException('Could not open return channel', 1620514205);
        }
        self::$returnChannels[] = $returnChannel;

        fputs($returnChannel, PackageSerializer::toString(new NoOpResponse()));
        try {
            $writeBack = new WriteBack($returnChannel);
            $job->run($writeBack);
        } catch (Exception $throwable) {
            fputs($returnChannel, PackageSerializer::toString(new ThrowableResponse($throwable)));
            throw $throwable;
        } finally {
            register_shutdown_function(static function () use ($returnChannel) {
                fputs($returnChannel, PackageSerializer::toString(new NoOpResponse()));
            });
        }
    }

    final protected function asParent(int $child): void
    {
        pcntl_waitpid($child, $statuscode);
    }
}
