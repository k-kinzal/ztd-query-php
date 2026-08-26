<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use InvalidArgumentException;

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

    public static function generatedIdentity(string $targetColumn, int $value): self
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Generated identity value must be positive.');
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
