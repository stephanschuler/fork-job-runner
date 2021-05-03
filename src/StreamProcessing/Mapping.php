<?php

namespace StephanSchuler\ForkJobRunner\StreamProcessing;

/**
 * @template In
 * @template Out
 */
interface Mapping
{
    /**
     * @param iterable<In> $source
     * @return iterable<Out>
     */
    public function map(iterable $source): iterable;
}
