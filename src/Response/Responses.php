<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Response;

use Generator;
use IteratorAggregate;
use StephanSchuler\ForkJobRunner\Dispatcher;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use function fclose;
use function feof;
use function fgets;
use function is_resource;
use function register_shutdown_function;
use function socket_set_blocking;
use function stream_select;
use function stream_socket_shutdown;

/**
 * @implements IteratorAggregate<Response>
 */
final class Responses implements IteratorAggregate
{
    /** @var Dispatcher */
    private $dispatcher;

    /** @var resource */
    private $socket;

    /**
     * @param Dispatcher $dispatcher
     * @param resource $socket
     */
    private function __construct(
        Dispatcher $dispatcher,
        $socket
    ) {
        $this->dispatcher = $dispatcher;
        $this->socket = $socket;
    }

    /**
     * @param Dispatcher $dispatcher
     * @param resource $socket
     * @return static
     */
    public static function create(
        Dispatcher $dispatcher,
        $socket
    ): self {
        return new static($dispatcher, $socket);
    }

    /** @return Generator<Response> */
    public function getIterator(): Generator
    {
        $socket = $this->socket;
        socket_set_blocking($socket, true);
        $shutdown = static function () use ($socket) {
            self::closeResource($socket);
        };
        register_shutdown_function($shutdown);

        while (true) {
            $read = [$socket];
            $write = null;
            $except = null;

            stream_select($read, $write, $except, 0, 100000);

            $responses = [];
            foreach ($read as $channel) {
                $content = fgets($channel);
                if ($content) {
                    $response = PackageSerializer::fromString($content);
                    $responses[] = $response;
                    if ($response instanceof Response) {
                        yield $response;
                    } else {
                        yield new InvalidResponse($response);
                    }
                }
            }

            if (feof($socket)) {
                break;
            }
        }
        $shutdown();
    }

    /**
     * @param ?resource $resource
     */
    private static function closeResource(&$resource): void
    {
        if (is_resource($resource)) {
            stream_socket_shutdown($resource, \STREAM_SHUT_RDWR);
            fclose($resource);
        }
    }
}
