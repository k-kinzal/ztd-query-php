<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

final class ProductionPattern
{
    private const CONTAINING = 'containing';
    private const EXACT = 'exact';
    private const NON_EMPTY = 'non-empty';

    /**
     * @param array<array-key, string> $symbols
     * @param self::CONTAINING|self::EXACT|self::NON_EMPTY $mode
     */
    private function __construct(
        private readonly array $symbols,
        private readonly string $mode,
    ) {
    }

    public static function containing(string ...$symbols): self
    {
        return new self($symbols, self::CONTAINING);
    }

    public static function exactly(string ...$symbols): self
    {
        return new self(array_values($symbols), self::EXACT);
    }

    public static function nonEmpty(): self
    {
        return new self([], self::NON_EMPTY);
    }

    /**
     * @param list<string> $symbols
     */
    public function matches(array $symbols): bool
    {
        if ($this->mode === self::EXACT) {
            return $symbols === $this->symbols;
        }
        if ($this->mode === self::NON_EMPTY) {
            return $symbols !== [];
        }
        foreach ($this->symbols as $required) {
            if (!in_array($required, $symbols, true)) {
                return false;
            }
        }

        return true;
    }
}
