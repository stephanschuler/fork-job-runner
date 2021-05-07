<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use StephanSchuler\ForkJobRunner\Loop;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use function file_put_contents;
use function proc_open;
use function sys_get_temp_dir;
use function tempnam;

class LoopTest extends TestCase
{
    /**
     * @test
     */
    public function Loop_calls_jobs_from_command_stream(): void
    {
        $phpcode = <<<'PHP'
<?PHP
declare(strict_types=1);

require getenv('AUTOLOADER');

use StephanSchuler\ForkJobRunner\Loop;

$loop = new Loop(STDIN);
$loop->run();
PHP;

        $tmpfile = self::tmpfile('phpcode');
        file_put_contents($tmpfile, $phpcode);

        $input = self::tmpfile('input');
        $output = self::tmpfile('output');

        $job = new TestJob('line 1', 'line 2');
        file_put_contents($input, PackageSerializer::toString($job));

        $proc = proc_open(
            'php ' . $tmpfile,
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

        self::assertStringEqualsFile($output, 'line 1' . PHP_EOL . 'line 2' . PHP_EOL);
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