<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Socket;

use IteratorAggregate;
use Traversable;
use function fgets;

/**
 * @implements \IteratorAggregate<string>
 */
final class Reader implements IteratorAggregate
{
    /** @var resource */
    private $source;

    /** @param resource $source */
    public function __construct($source)
    {
        $this->source = $source;
    }

    /** @return Traversable<string> */
    public function getIterator(): Traversable
    {
        while (($data = fgets($this->source)) !== false) {
            yield $data;
        }
    }
}
