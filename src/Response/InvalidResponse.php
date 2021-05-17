<?php
declare(strict_types=1);

namespace StephanSchuler\ForkJobRunner\Response;

final class InvalidResponse implements Response
{
    /** @var mixed */
    protected $data;

    /** @param mixed $data */
    public function __construct($data)
    {
        $this->data = $data;
    }
}
