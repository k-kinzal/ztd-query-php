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
    /**
     * Binds one column of the projection to exactly one of the four things it can be.
     *
     * Every way of building one goes through a named constructor, because which of
     * the four it is decides what the SELECT says in its place.
     */
    public function __construct(
        private readonly string $targetColumn,
        private readonly ?string $providedExpression,
        private readonly ?string $defaultExpression,
        private readonly ?int $generatedIdentityValue,
        private readonly bool $nullValue,
    ) {
    }

    /**
     * Provided.
     *
     * @param string $targetColumn
     * @param string $expression
     * @return self
     */
    public static function provided(string $targetColumn, string $expression): self
    {
        return new self($targetColumn, $expression, null, null, false);
    }

    /**
     * Default expression.
     *
     * @param string $targetColumn
     * @param string $expression
     * @return self
     */
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

    /**
     * Null value.
     *
     * @param string $targetColumn
     * @return self
     */
    public static function nullValue(string $targetColumn): self
    {
        return new self($targetColumn, null, null, null, true);
    }

    /**
     * Target column.
     *
     * @return string
     */
    public function targetColumn(): string
    {
        return $this->targetColumn;
    }

    /**
     * Provided expression.
     *
     * @return ?string
     */
    public function providedExpression(): ?string
    {
        return $this->providedExpression;
    }

    /**
     * Default expression value.
     *
     * @return ?string
     */
    public function defaultExpressionValue(): ?string
    {
        return $this->defaultExpression;
    }

    /**
     * Generated identity value.
     *
     * @return ?int
     */
    public function generatedIdentityValue(): ?int
    {
        return $this->generatedIdentityValue;
    }

    /**
     * Reports whether null value.
     *
     * @return bool
     */
    public function isNullValue(): bool
    {
        return $this->nullValue;
    }
}
