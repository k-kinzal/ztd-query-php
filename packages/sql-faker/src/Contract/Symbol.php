<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final readonly class Symbol
{
    public function __construct(
        public string $name,
        public bool $isNonTerminal,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('Symbol name must be non-empty.');
        }
    }
}
