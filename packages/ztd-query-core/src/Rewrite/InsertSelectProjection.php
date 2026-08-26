<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Exception\InvalidDefinitionException;

final class InsertSelectProjection
{
    private function __construct(
        private readonly string $targetColumn,
        private readonly ?int $sourceIndex,
        private readonly ?string $defaultExpression,
        private readonly ?int $generatedIdentityStart,
        private readonly bool $nullValue,
    ) {
    }

    public static function source(string $targetColumn, int $sourceIndex): self
    {
        if ($sourceIndex < 0) {
            throw new InvalidDefinitionException('Source index must not be negative.');
        }

        return new self($targetColumn, $sourceIndex, null, null, false);
    }

    public static function defaultExpression(string $targetColumn, string $expression): self
    {
        return new self($targetColumn, null, $expression, null, false);
    }

    public static function generatedIdentity(string $targetColumn, int $start): self
    {
        if ($start < 1) {
            throw new InvalidDefinitionException('Generated identity start must be positive.');
        }

        return new self($targetColumn, null, null, $start, false);
    }

    public static function nullValue(string $targetColumn): self
    {
        return new self($targetColumn, null, null, null, true);
    }

    public function targetColumn(): string
    {
        return $this->targetColumn;
    }

    public function sourceIndex(): ?int
    {
        return $this->sourceIndex;
    }

    public function defaultExpressionValue(): ?string
    {
        return $this->defaultExpression;
    }

    public function generatedIdentityStart(): ?int
    {
        return $this->generatedIdentityStart;
    }

    public function isNullValue(): bool
    {
        return $this->nullValue;
    }
}
