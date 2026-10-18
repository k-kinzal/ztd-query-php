<?php

declare(strict_types=1);

namespace Harbor\Http;

use RuntimeException;

final class Problem extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
