<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use Exception;
use RuntimeException;
use StephanSchuler\ForkJobRunner\Response\NoOpResponse;
use StephanSchuler\ForkJobRunner\Response\ThrowableResponse;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use StephanSchuler\ForkJobRunner\Utility\WriteBack;
use function array_filter;
use function assert;
use function cli_set_process_title;
use function fclose;
use function fgets;
use function fopen;
use function fputs;
use function pcntl_fork;
use function pcntl_waitpid;
use function posix_kill;
use function register_shutdown_function;
use function stream_select;
use function stream_set_blocking;
use function trim;
use const SIGTERM;
use const WUNTRACED;
use const WNOHANG;

final class Loop
{
    /** @var string */
    private $commandChannel;

    /** @var int[] */
    private $children = [];

    public function __construct(string $commandChannel)
    {
        $this->commandChannel = $commandChannel;
    }

    public static function create(): self
    {
        return new static('php://stdin');
    }

    public function readFrom(string $commandChannel): self
    {
        return new static($commandChannel);
    }

    public function run(): void
    {
        $commandChannel = fopen($this->commandChannel, 'rb');
        if (!$commandChannel) {
            throw new RuntimeException('Could not open command channel', 1620514191);
        }

        $children = [];
        stream_set_blocking($commandChannel, false);

        while (true) {
            $read = [$commandChannel];
            $write = null;
            $except = null;

            stream_select($read, $write, $except, 1);

            foreach ($read as $channel) {
                $data = trim((string)fgets($channel), PackageSerializer::SPLITTER);
                $child = pcntl_fork();

                if ($child === -1) {
                    throw new RuntimeException('Could not fork into isolated execution', 1620514200);
                } elseif ($child === 0) {
                    $this->asChild($data);
                    // Children stop looping after doing the work
                    return;
                } else {
                    $this->asParent($child);
                    $children[] = $child;
                }

            }
            if (feof($commandChannel)) {
                break;
            }
        }

        @fclose($commandChannel);

        $this->clearChildren();
        foreach ($children as $child) {
            @posix_kill($child, SIGTERM);
        }
    }

    private function asParent(int $child): void
    {
        cli_set_process_title('I am the parent!');
        $this->children[] = $child;
        $this->clearChildren();
    }

    private function clearChildren(): void
    {
        $this->children = array_filter($this->children, static function (int $child) {
            $res = pcntl_waitpid($child, $status, WUNTRACED | WNOHANG);
            return !($res == -1 || $res > 0);
        });
    }

    private function asChild(string $data): void
    {
        cli_set_process_title('I am the child!');
        $job = PackageSerializer::fromString($data);
        assert($job instanceof Job);

        $returnChannelPath = $job->getReturnChannel();
        $returnChannel = fopen($returnChannelPath, 'wb+');
        if (!$returnChannel) {
            throw new RuntimeException('Could not open return channel', 1620514205);
        }

        fputs($returnChannel, PackageSerializer::toString(new NoOpResponse()));
        try {
            $writeBack = new WriteBack($returnChannel);
            $job->run($writeBack);

            register_shutdown_function(static function () use (&$returnChannel) {
                @fclose($returnChannel);
            });
        } catch (Exception $throwable) {
            fputs($returnChannel, PackageSerializer::toString(new ThrowableResponse($throwable)));
            throw $throwable;
        } finally {
            fputs($returnChannel, PackageSerializer::toString(new NoOpResponse()));
        }
    }
}
