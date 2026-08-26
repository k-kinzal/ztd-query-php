<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Exception\InvalidDefinitionException;

final class InsertSelectProjection
{
    /**
     * Binds one column of the projection to exactly one of the four things it can be.
     *
     * Every way of building one goes through a named constructor, because which of
     * the four it is decides what the SELECT says in its place.
     */
    public function __construct(
        private readonly string $targetColumn,
        private readonly ?int $sourceIndex,
        private readonly ?string $defaultExpression,
        private readonly ?int $generatedIdentityStart,
        private readonly bool $nullValue,
    ) {
    }

    /**
     * @throws InvalidDefinitionException When the position is before the start of the projection
     */
    public static function source(string $targetColumn, int $sourceIndex): self
    {
        if ($sourceIndex < 0) {
            throw new InvalidDefinitionException('Source index must not be negative.');
        }

        return new self($targetColumn, $sourceIndex, null, null, false);
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
     * @throws InvalidDefinitionException When the first identity is not a positive number
     */
    public static function generatedIdentity(string $targetColumn, int $start): self
    {
        if ($start < 1) {
            throw new InvalidDefinitionException('Generated identity start must be positive.');
        }

        return new self($targetColumn, null, null, $start, false);
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
     * Source index.
     *
     * @return ?int
     */
    public function sourceIndex(): ?int
    {
        return $this->sourceIndex;
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
     * Generated identity start.
     *
     * @return ?int
     */
    public function generatedIdentityStart(): ?int
    {
        return $this->generatedIdentityStart;
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
