<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner;

use Exception;
use RuntimeException;
use StephanSchuler\ForkJobRunner\Dispatcher\FollowingDispatcher;
use StephanSchuler\ForkJobRunner\Dispatcher\LeadingDispatcher;
use StephanSchuler\ForkJobRunner\Response\Response;
use StephanSchuler\ForkJobRunner\Response\Responses;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use function fopen;
use function fputs;
use function getenv;
use function stream_socket_client;
use function is_resource;

abstract class Dispatcher
{
    const FORK_QUEUE_COMMAND_CHANNEL = 'FORK_QUEUE_COMMAND_CHANNEL';

    /** @var ?string */
    protected $socketFileName;

    abstract protected function __construct(string $_);

    public static function create(string $loopCommand): self
    {
        $socketFileName = (string)getenv(self::FORK_QUEUE_COMMAND_CHANNEL);
        if ($socketFileName === '') {
            return new LeadingDispatcher($loopCommand);
        } else {
            return new FollowingDispatcher($socketFileName);
        }
    }

    /**
     * @param Job $job
     * @return Responses<Response>
     */
    public function run(Job $job): Responses
    {
        if ($this instanceof LeadingDispatcher) {
            $this->ensureLoop();
        }
        $socket = $this->getSocket();
        if (!is_resource($socket)) {
            throw new RuntimeException('Socket unavailable', 1621677645);
        }

        $put = fputs($socket, PackageSerializer::toString($job));
        if ($put === false) {
            throw new RuntimeException('Command channel unavailable', 1620514181);
        }

        return Responses::create(
            $this,
            $socket
        );
    }

    /**
     * @return false|resource
     */
    protected function getSocket()
    {
        $socketFileName = 'unix://' . $this->socketFileName;

        $socket = false;
        $end = time() + 15;
        while (!$socket) {
            $socket = @stream_socket_client(
                $socketFileName,
                $errno,
                $errstr,
                1
            );
            if (time() >= $end) {
                break;
            }
        }
        return $socket;
    }
}
