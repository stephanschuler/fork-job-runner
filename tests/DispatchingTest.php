<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use StephanSchuler\ForkJobRunner\Dispatcher;
use function file_put_contents;
use function iterator_to_array;
use function sys_get_temp_dir;
use function tempnam;

class DispatchingTest extends TestCase
{
    /** @test */
    public function Dispatcher_call_jobs_and_return_data(): void
    {
        $job = new TestJob('first line', 'second line');

        putenv('AUTOLOADER=' . __DIR__ . '/../vendor/autoload.php');
        $phpcode = <<<'PHP'
<?PHP
declare(strict_types=1);

require getenv('AUTOLOADER');

use StephanSchuler\ForkJobRunner\Loop;

$loop = new Loop('php://stdin', getenv('RETURN_CHANNEL'));
$loop->run();
PHP;
        $loop = self::tmpfile('loop');
        file_put_contents($loop, $phpcode);

        $dispatcher = new Dispatcher($loop);
        $result = $dispatcher->run($job);

        self::assertInstanceOf(\Generator::class, $result);
        self::assertEquals(
            ['noop' . PHP_EOL, 'first line' . PHP_EOL, 'second line' . PHP_EOL],
            iterator_to_array($result)
        );
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