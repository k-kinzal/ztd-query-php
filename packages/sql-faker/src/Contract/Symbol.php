<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final class Symbol
{
    public function __construct(
        public readonly string $name,
        public readonly bool $isNonTerminal,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('Symbol name must be non-empty.');
        }
    }
}
