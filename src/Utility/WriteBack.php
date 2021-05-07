<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Utility;

use StephanSchuler\ForkJobRunner\Response\Response;

class WriteBack
{
    /** @var resource */
    private $returnChannel;

    /** @param resource $returnChannel */
    public function __construct($returnChannel)
    {
        $this->returnChannel = $returnChannel;
    }

    public function send(Response $response): void
    {
        fputs($this->returnChannel, PackageSerializer::toString($response));
    }
}