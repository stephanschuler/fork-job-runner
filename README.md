Fork Job Runner
===============

Creating child processes is expensive, especially when this comes with
bootstrapping huge parts of a framework.

This package constantly holds a single PHP process at a certain bootstrap level
and forks this process whenever a job is to be executed.

All the demo code blow is to be implemented by the project, it is not provided
by this package.


Jobs
----

Every code that should be run in a separate process is called a `Job`. Jobs are
created by the parent, serialized, passed to the worker child and unserialized
prior to execution. This needs to be considered, especially when dealing with
database connections, file pointers and other resources.

```php
<?php
declare(strict_types=1);

namespace StephanSchuler\Demo;

use StephanSchuler\ForkJobRunner\BackChannel\WriteChannel;
use StephanSchuler\ForkJobRunner\Job;

class DemoJob implements Job
{
    public function run(WriteChannel $writer): void
    {
        // Do something
    }
}
```

The `Job` is a regular class definition. Put it in your project where it can be
found by the autoloader.


Dispatching
-----------

Passing a job from the parent process to isolated execution is called
dispatching. There is no routing mechanism to hand jobs to different works nor
is it this packages intention to execute jobs asynchronously or in parallel.

```php
<?php
declare(strict_types=1);

namespace StephanSchuler\Demo;

use StephanSchuler\ForkJobRunner\Dispatcher;

$dispatcher = new Dispatcher(
    __DIR__ . '/loop.php'
);

$dispatcher->run(
    new DemoJob()
);
```

Creating the dispatcher should be done only once because each dispatcher keeps
its own worker process around.

Dispatching should be done wherever needed, e.g. within action methods in MVC
controllers or in test methods of unit tests.


The Loop
--------

The `Loop` is the child process waiting for incoming `Job`s. The `Dispatcher`
initializes the `Loop` and passes `Job`s along.

This is meant to be a CLI entry point. It's the "loop.php" file mentioned in the
example code of the `Dispatcher`.

```php
<?php
declare(strict_types=1);

use StephanSchuler\ForkJobRunner\Loop;

require(__DIR__ . '/vendor/autoload.php');

// Bootstrap framework here

(new Loop())
    ->run();
```

The `Loop::run()` method does never return. It iterates infinitely and exists
with "broken pipe" once the command channel dies. There should be no code below
this method because it is unreachable.


Responding
----------

Jobs can respond to the dispatching process.
Every serializable data can be passed from `Job` to `Dispatcher`. 

```php
<?php
declare(strict_types=1);

use StephanSchuler\ForkJobRunner\BackChannel\WriteChannel;
use StephanSchuler\ForkJobRunner\Dispatcher;
use StephanSchuler\ForkJobRunner\Job;
use StephanSchuler\ForkJobRunner\Response;

class DemoJob implements Job
{
    public function run(WriteChannel $writer) : void
    {
        $writer->respond(
            new Response\DefaultResponse('this goes to stdout')
        );
        $writer->respond(
            new Response\ErrorResponse('this goes to stderr')
        );
        $writer->respond(
            new Response\ThrowableResponse(
                new RuntimeException('This is an exception')
            )
        );
        throw new RuntimeException('This is an exception as well');
    }
}

assert($dispatcher instanceof Dispatcher);

$job = new DemoJob();
$responses = $dispatcher->run($job);

foreach ($responses as $response) {
    switch (true) {
        case ($response instanceof Response\NoOpResponse):
            // No Op responses act as keep alive signal.
            // There is at least one per job, at its beginning.
            break;
        case ($response instanceof Response\DefaultResponse):
            // Default responses contain text.
            // They are not used internally.
            fputs(\STDOUT, $response->get());
            break;
        case ($response instanceof Response\ErrorResponse):
            // Error responses contain text as well.
            // They are also not used internally.
            fputs(\STDERR, $response->get());
            break;
        case ($response instanceof Response\ThrowableResponse):
            // This catches both,
            // the explicite call of $writer->respond()
            // as well as the thrown exception
            fputs(\STDERR, print_r($response->get(), true));
            break;
       case ($response instanceof Response\Response):
            // Feel free to implement custom responses.
            break;
    }
}
```