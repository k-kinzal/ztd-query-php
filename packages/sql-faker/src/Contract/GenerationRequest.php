<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final class GenerationRequest
{
    public function __construct(
        public readonly ?string $startRule = null,
        public readonly ?int $seed = null,
        public readonly int $maxDepth = PHP_INT_MAX,
    ) {
        if ($this->startRule !== null && $this->startRule === '') {
            throw new InvalidArgumentException('startRule must be a non-empty string when provided.');
        }
    }
}
