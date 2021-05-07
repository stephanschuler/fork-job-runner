<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Response;

use Throwable;

final class ThrowableResponse implements Response
{
    /** @var Throwable */
    private $subject;

    public function __construct(Throwable $subject)
    {
        $this->subject = $subject;
    }

    public function get(): Throwable
    {
        return $this->subject;
    }
}