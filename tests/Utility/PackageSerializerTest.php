<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Tests\Utility;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;

class PackageSerializerTest extends TestCase
{
    const SERIALIZED = 'TzoxNzoiRGF0ZVRpbWVJbW11dGFibGUiOjM6e3M6NDoiZGF0ZSI7czoyNjoiMjAyMC0wNS0wNyAwMToyNDowMy4wMDAwMDAiO3M6MTM6InRpbWV6b25lX3R5cGUiO2k6MTtzOjg6InRpbWV6b25lIjtzOjY6IiswMjowMCI7fQ==' . PHP_EOL;
    const UNSERIALIZED = '2020-05-07 01:24:03 GMT+2';

    /**
     * @test
     */
    public function can_serialize(): void
    {
        $date = new DateTimeImmutable(self::UNSERIALIZED);
        $serialized = PackageSerializer::toString($date);

        self::assertEquals(
            self::SERIALIZED,
            $serialized
        );
    }

    /**
     * @test
     */
    public function can_unserialize(): void
    {
        $date = new DateTimeImmutable(self::UNSERIALIZED);
        $unserializd = PackageSerializer::fromString(self::SERIALIZED);

        self::assertEquals(
            $date,
            $unserializd
        );
    }
}