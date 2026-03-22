<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final class RewriteStep
{
    public function __construct(
        public readonly string $id,
        public readonly string $description,
    ) {
        if ($this->id === '') {
            throw new InvalidArgumentException('Rewrite step id must be a non-empty string.');
        }

        if ($this->description === '') {
            throw new InvalidArgumentException('Rewrite step description must be a non-empty string.');
        }
    }
}
