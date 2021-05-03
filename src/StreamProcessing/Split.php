<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\StreamProcessing;

use function array_shift;
use function count;
use function explode;

/**
 * @implements Mapping<string, string>
 */
class Split implements Mapping
{
    /** @var string */
    private $split;

    public function __construct(string $split)
    {
        $this->split = $split;
    }

    /**
     * @param iterable<string> $source
     * @return iterable<string>
     */
    public function map(iterable $source): iterable
    {
        $next = '';
        foreach ($source as $batch) {
            $next .= $batch;
            $all = explode($this->split, $next);
            /** @phpstan-ignore-next-line */
            if ($all === false) {
                continue;
            }
            while (count($all) > 1) {
                $next = array_shift($all);
                yield $next;
            }
            $next = $all[0];
        }

        yield $next;
    }
}
