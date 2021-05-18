<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Response;

use Generator;
use IteratorAggregate;
use RuntimeException;
use StephanSchuler\ForkJobRunner\Dispatcher;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use function fclose;
use function feof;
use function fgets;
use function is_resource;
use function register_shutdown_function;
use function stream_select;

/**
 * @implements IteratorAggregate<Response>
 */
final class Responses implements IteratorAggregate
{
    /** @var Dispatcher */
    private $dispatcher;

    /** @var string */
    private $returnChannelPath;

    /** @var ?resource */
    private $returnChannel;

    /** @var ?resource */
    private $blockReturnChannel;

    /**
     * @param Dispatcher $dispatcher
     * @param string $returnChannelPath
     * @param resource $returnChannel
     * @param resource $blockReturnChannel
     */
    private function __construct(
        Dispatcher $dispatcher,
        string $returnChannelPath,
        $returnChannel,
        $blockReturnChannel
    ) {
        $this->dispatcher = $dispatcher;
        $this->returnChannelPath = $returnChannelPath;
        $this->returnChannel = $returnChannel;
        $this->blockReturnChannel = $blockReturnChannel;
    }

    /**
     * @param Dispatcher $dispatcher
     * @param string $returnChannelPath
     * @param resource $returnChannel
     * @param resource $blockReturnChannel
     * @return static
     */
    public static function create(
        Dispatcher $dispatcher,
        string $returnChannelPath,
        $returnChannel,
        $blockReturnChannel
    ): self {
        if (!is_resource($returnChannel)) {
            throw new RuntimeException('Return channel is not open', 1621281176);
        }
        if (!is_resource($blockReturnChannel)) {
            throw new RuntimeException('Return channel blocker is not open', 1621281180);
        }

        return new static($dispatcher, $returnChannelPath, $returnChannel, $blockReturnChannel);
    }

    /** @return Generator<Response> */
    public function getIterator(): Generator
    {
        if (!is_resource($this->returnChannel)) {
            return;
        }
        $returnChannel = $this->returnChannel;
        $this->returnChannel = null;

        if (!is_resource($this->blockReturnChannel)) {
            return;
        }
        $blockReturnChannel = $this->blockReturnChannel;
        $this->blockReturnChannel = null;

        $returnChannelPath = $this->returnChannelPath;

        $this->dispatcher->checkForRunningLoop();

        $shutdown = static function () use ($blockReturnChannel, $returnChannel, $returnChannelPath) {
            self::closeResource($blockReturnChannel);
            self::closeResource($returnChannel);
            @unlink($returnChannelPath);
        };
        register_shutdown_function($shutdown);

        while (true) {
            $read = [$returnChannel];
            $write = null;
            $except = null;

            stream_select($read, $write, $except, 1);

            self::closeResource($blockReturnChannel);

            foreach ($read as $channel) {
                $content = fgets($channel);
                if ($content) {
                    $response = PackageSerializer::fromString($content);
                    if ($response instanceof Response) {
                        yield $response;
                    } else {
                        yield new InvalidResponse($response);
                    }
                }
            }

            $this->dispatcher->checkForRunningLoop();

            if (feof($returnChannel)) {
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
            @fclose($resource);
            $resource = null;
        }
    }
}
