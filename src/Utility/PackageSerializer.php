<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Utility;

use StephanSchuler\ForkJobRunner\StreamProcessing;
use function base64_encode;
use function serialize;
use const PHP_EOL;

final class PackageSerializer
{
    const SPLITTER = PHP_EOL;

    private function __construct()
    {
    }

    /**
     * @param mixed $subject
     * @return string
     */
    public static function toString($subject): string
    {
        return
            base64_encode(
                serialize($subject)
            ) . self::SPLITTER;
    }

    /**
     * @param string $string
     * @return mixed|null
     */
    public static function fromString(string $string)
    {
        $subjects = self::processor()
            ->map([$string]);
        foreach ($subjects as $subject) {
            return $subject;
        }
        return null;
    }

    /**
     * @param \Traversable<string> $traversable
     * @return \Traversable<mixed>
     */
    public static function fromTraversable(\Traversable $traversable): \Traversable
    {
        $subjects = self::processor()
            ->map($traversable);
        foreach ($subjects as $subject) {
            yield $subject;
        }
    }

    /**
     * @return StreamProcessing\Processor<string, mixed>
     */
    private static function processor(): StreamProcessing\Processor
    {
        return new StreamProcessing\Processor(
            new StreamProcessing\Split(self::SPLITTER),
            new StreamProcessing\Filter(),
            new StreamProcessing\Map('base64_decode'),
            new StreamProcessing\Map('unserialize')
        );
    }
}
