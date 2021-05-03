<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\StreamProcessing;

/**
 * @template T_In
 * @template T_Out
 * @implements Mapping<T_In, T_Out>
 */
class Map implements Mapping
{
    /** @var callable(T_In):T_Out */
    private $callable;

    /** @param callable(T_In):T_Out $callable */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @param iterable<T_In> $source
     * @return iterable<T_Out>
     */
    public function map(iterable $source): iterable
    {
        foreach ($source as $item) {
            yield ($this->callable)($item);
        }
    }
}
