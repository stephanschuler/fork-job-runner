<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Utility;

class PackageSerializer
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
        return unserialize(
            base64_decode(
                trim($string, self::SPLITTER)
            )
        );
    }
}