<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\StreamProcessing;

use function func_get_args;

/**
 * @template T_In
 * @template T_Out
 * @implements Mapping<T_In, mixed>
 */
class Processor implements Mapping
{
    /** @var Mapping<mixed, mixed>[] */
    private $mappings;

    /**
     * @param Mapping<T_In, mixed> $mapping
     * @param Mapping<mixed, T_Out> ... $mappings
     */
    public function __construct(Mapping $mapping, Mapping ...$mappings)
    {
        $this->mappings = func_get_args();
    }

    /**
     * @param iterable<T_In> $source
     * @return iterable<T_Out>
     */
    public function map(iterable $source): iterable
    {
        foreach ($this->mappings as $mapping) {
            $source = $mapping->map($source);
        }
        yield from $source;
    }
}
