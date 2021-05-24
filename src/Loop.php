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
use function fclose;
use function feof;
use function fgets;
use function fputs;
use function getenv;
use function is_resource;
use function pcntl_fork;
use function pcntl_waitpid;
use function posix_getppid;
use function posix_kill;
use function register_shutdown_function;
use function stream_select;
use function stream_socket_accept;
use function stream_socket_server;
use function stream_socket_shutdown;
use function unlink;
use const SIGTERM;
use const WNOHANG;
use const WUNTRACED;

final class Loop
{
    /** @var string */
    private $socketFileName;

    /** @var int[] */
    private $children = [];

    public function __construct(string $socketFileName)
    {
        $this->socketFileName = $socketFileName;
    }

    public static function create(): self
    {
        return new static(
            (string)getenv(Dispatcher::FORK_QUEUE_COMMAND_CHANNEL)
        );
    }

    public function run(): ?DeferredConnectionShutdown
    {
        $socketFileName = 'unix://' . $this->socketFileName;
        $socket = stream_socket_server($socketFileName, $errno, $errstr);
        if (!is_resource($socket)) {
            $explanation = join(': ', [$errno, $errstr]);
            if ($explanation !== '') {
                $explanation = '(' . $explanation . ')';
            }
            throw new RuntimeException('Could not create socket: "' . $this->socketFileName . $explanation, 1620514191);
        }

        $children = [];

        while (true) {
            if (posix_getppid() <= 1) {
                // Parent is terminated.
                break;
            }

            if (!is_resource($socket)) {
                // Socket is closed
                break;
            }

            $connection = @stream_socket_accept($socket, 1);
            if (!is_resource($connection)) {
                continue;
            }

            $child = pcntl_fork();

            if ($child === -1) {
                throw new RuntimeException('Could not fork into isolated execution', 1620514200);
            } elseif ($child === 0) {
                return $this->asChild($connection);
            } else {
                $this->asParent($child);
                $children[] = $child;
            }
        }

        fclose($socket);

        $this->clearChildren();
        foreach ($children as $child) {
            posix_kill($child, SIGTERM);
        }
        @unlink($this->socketFileName);

        return null;
    }

    private function asParent(int $child): void
    {
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

    /** @param resource $connection */
    private function asChild($connection): ?DeferredConnectionShutdown
    {
        while (true) {
            if (!is_resource($connection) || feof($connection)) {
                stream_socket_shutdown($connection, \STREAM_SHUT_RDWR);
                fclose($connection);
                return null;
            }
            $read = [$connection];
            $write = $except = null;
            stream_select($read, $write, $except, 0, 100000);
            foreach ($read as $socket) {
                $data = fgets($socket);
                if ($data === false || $data === '') {
                    continue;
                }
                return $this->processJob($connection, $data);
            }
        }
    }

    /**
     * @param resource $connection
     * @param string $data
     */
    private function processJob($connection, string $data): DeferredConnectionShutdown
    {
        $job = PackageSerializer::fromString($data);
        assert($job instanceof Job);

        fputs($connection, PackageSerializer::toString(new NoOpResponse()));
        $deferredConnectionShutdown = new DeferredConnectionShutdown($connection);
        try {
            $writeBack = new WriteBack($connection);
            $job->run($writeBack);
            fputs($connection, PackageSerializer::toString(new NoOpResponse()));
            return $deferredConnectionShutdown;
        } catch (Exception $throwable) {
            fputs($connection, PackageSerializer::toString(new ThrowableResponse($throwable)));
            $deferredConnectionShutdown->close();
            throw $throwable;
        }
    }
}
