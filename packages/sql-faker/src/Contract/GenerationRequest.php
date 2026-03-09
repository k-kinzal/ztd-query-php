<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final readonly class GenerationRequest
{
    public function __construct(
        public ?string $startRule = null,
        public ?int $seed = null,
        public int $maxDepth = PHP_INT_MAX,
    ) {
        if ($this->startRule !== null && $this->startRule === '') {
            throw new InvalidArgumentException('startRule must be a non-empty string when provided.');
        }
    }
}
