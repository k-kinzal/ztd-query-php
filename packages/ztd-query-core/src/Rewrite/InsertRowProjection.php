<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * What one column of a rewritten INSERT ... VALUES will read back as.
 *
 * A rewritten INSERT does not insert: it selects the row it would have
 * written. Every column of the target therefore has to be accounted for,
 * including the ones the statement left out, because a row read back without
 * them is not the row a database would have stored. A column is written into
 * the projection as exactly one of four things, and which one it is decides
 * what the SELECT says in its place.
 */
final class InsertRowProjection
{
    private function __construct(
        private readonly string $targetColumn,
        private readonly ?string $providedExpression,
        private readonly ?string $defaultExpression,
        private readonly ?int $generatedIdentityValue,
        private readonly bool $nullValue,
    ) {
    }

    public static function provided(string $targetColumn, string $expression): self
    {
        return new self($targetColumn, $expression, null, null, false);
    }

    public static function defaultExpression(string $targetColumn, string $expression): self
    {
        return new self($targetColumn, null, $expression, null, false);
    }

    /**
     * @throws InvalidDefinitionException When the identity is not a positive number
     */
    public static function generatedIdentity(string $targetColumn, int $value): self
    {
        if ($value < 1) {
            throw new InvalidDefinitionException('Generated identity value must be positive.');
        }

        return new self($targetColumn, null, null, $value, false);
    }

    public static function nullValue(string $targetColumn): self
    {
        return new self($targetColumn, null, null, null, true);
    }

    public function targetColumn(): string
    {
        return $this->targetColumn;
    }

    public function providedExpression(): ?string
    {
        return $this->providedExpression;
    }

    public function defaultExpressionValue(): ?string
    {
        return $this->defaultExpression;
    }

    public function generatedIdentityValue(): ?int
    {
        return $this->generatedIdentityValue;
    }

    public function isNullValue(): bool
    {
        return $this->nullValue;
    }
}
