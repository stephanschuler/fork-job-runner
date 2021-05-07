<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use StephanSchuler\ForkJobRunner\Dispatcher;
use StephanSchuler\ForkJobRunner\Response\DefaultResponse;
use StephanSchuler\ForkJobRunner\Response\NoOpResponse;
use StephanSchuler\ForkJobRunner\Utility\PackageSerializer;
use function iterator_to_array;
use function sys_get_temp_dir;
use function tempnam;

class DispatchingTest extends TestCase
{
    /** @var Dispatcher */
    protected $dispatcher;

    public function setUp(): void
    {
        parent::setUp();

        putenv('AUTOLOADER=' . __DIR__ . '/../vendor/autoload.php');
        $this->dispatcher = new Dispatcher(__DIR__ . '/Fixtures/loop-to-configurable-output.php');
    }

    /** @test */
    public function Dispatcher_call_jobs_and_return_data(): void
    {
        $firstLineText = 'first line';
        $secondLineText = 'second line';

        $job = new TestJob($firstLineText, $secondLineText);
        $result = $this->dispatcher->run($job);

        self::assertInstanceOf(\Generator::class, $result);
        self::assertEquals(
            [
                PackageSerializer::toString(new NoOpResponse()),
                PackageSerializer::toString(new DefaultResponse($firstLineText)),
                PackageSerializer::toString(new DefaultResponse($secondLineText)),
            ],
            iterator_to_array($result)
        );
    }

    /**
     * @test
     */
    public function Dispatchers_can_run_multiple_jobs_one_at_a_time(): void
    {
        $first = false;
        (function () use (&$first) {
            $firstLineText = 'first line';
            $secondLineText = 'second line';

            $job1 = new TestJob($firstLineText, $secondLineText);
            $result1 = $this->dispatcher->run($job1);

            self::assertInstanceOf(\Generator::class, $result1);
            self::assertEquals(
                [
                    PackageSerializer::toString(new NoOpResponse()),
                    PackageSerializer::toString(new DefaultResponse($firstLineText)),
                    PackageSerializer::toString(new DefaultResponse($secondLineText)),
                ],
                iterator_to_array($result1)
            );

            $first = true;
        })();

        $second = false;
        (function () use (&$second) {
            $thirdLineText = 'third line';
            $fourthLineText = 'fourth line';

            $job2 = new TestJob($thirdLineText, $fourthLineText);
            $result2 = $this->dispatcher->run($job2);

            self::assertInstanceOf(\Generator::class, $result2);
            self::assertEquals(
                [
                    PackageSerializer::toString(new NoOpResponse()),
                    PackageSerializer::toString(new DefaultResponse($thirdLineText)),
                    PackageSerializer::toString(new DefaultResponse($fourthLineText)),
                ],
                iterator_to_array($result2)
            );
            $second = true;
        })();

        if (!$first) {
            self::markTestIncomplete('Job one did not run');
        }
        if (!$second) {
            self::markTestIncomplete('Job two did not run');
        }
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