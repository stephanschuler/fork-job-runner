<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\StreamProcessing;

use function is_callable;

/**
 * @template T_Subject
 * @implements Mapping<T_Subject, T_Subject>
 */
class Filter implements Mapping
{
    /** @var callable(T_Subject):bool|null */
    private $filter;

    /** @param callable(T_Subject):bool|null $filter */
    public function __construct(?callable $filter = null)
    {
        $this->filter = $filter;
    }

    /**
     * @param iterable<T_Subject> $source
     * @return iterable<T_Subject>
     */
    public function map(iterable $source): iterable
    {
        foreach ($source as $next) {
            switch (true) {
                case !is_callable($this->filter) && (bool)$next:
                case is_callable($this->filter) && ($this->filter)($next):
                    yield $next;
            }
        }
    }
}
