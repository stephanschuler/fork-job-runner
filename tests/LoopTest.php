<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use StephanSchuler\ForkJobRunner\Response\DefaultResponse;
use StephanSchuler\ForkJobRunner\Response\NoOpResponse;
use StephanSchuler\ForkJobRunner\Tests\Fixtures\DefaultResponseJob;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use function file_put_contents;
use function proc_open;
use function sys_get_temp_dir;
use function tempnam;

class LoopTest extends TestCase
{
    /** @test */
    public function Loop_calls_jobs_from_command_stream(): void
    {
        $input = self::tmpfile('input');
        $output = self::tmpfile('output');

        $lineOneText = 'line 1 text';
        $lineTwoText = 'line 2 text';

        $job = new DefaultResponseJob($lineOneText, $lineTwoText);
        file_put_contents($input, PackageSerializer::toString($job));

        $proc = proc_open(
            'php ' . escapeshellarg(__DIR__ . '/Fixtures/loop-to-stdout.php'),
            [['file', $input, 'r'], ['file', $output, 'w'], ['file', $output, 'w'],],
            $pipes,
            getcwd() ?: null,
            ['AUTOLOADER' => __DIR__ . '/../vendor/autoload.php',]
        );
        if (!$proc) {
            self::markTestIncomplete('sub process could not be created');
            return;
        }

        while (true) {
            $status = proc_get_status($proc);
            if (is_array($status) && $status['running'] === false) {
                break;
            }
        }
        proc_close($proc);

        $expected = join('', [
            PackageSerializer::toString(new NoOpResponse()),
            PackageSerializer::toString(new DefaultResponse($lineOneText)),
            PackageSerializer::toString(new DefaultResponse($lineTwoText))
        ]);

        self::assertStringEqualsFile($output, $expected);
    }

    protected static function tmpfile(string $reason): string
    {
        $tmpfile = tempnam(sys_get_temp_dir(), $reason);

        if (!$tmpfile) {
            $message = 'Could not create tmpfile "' . $reason . '"';
            self::markTestIncomplete($message);
            throw new Exception($message);
        }

        return $tmpfile;
    }
}