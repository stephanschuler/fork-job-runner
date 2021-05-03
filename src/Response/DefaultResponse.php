<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Response;

final class DefaultResponse implements Response
{
    /** @var string */
    private $subject;

    public function __construct(string $subject)
    {
        $this->subject = $subject;
    }

    public function get(): string
    {
        return $this->subject;
    }
}
