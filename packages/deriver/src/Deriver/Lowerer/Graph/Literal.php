<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Graph;

use LogicException;

/**
 * Literal syntax captured during lowering; it contains no evaluation state.
 * @visibility root
 */
final class Literal
{
    /**
     * @param scalar|null $literal Literal token or enum case identity
     * @param array<array-key, self> $operands Enum case name and backing literal
     * @param array<string, scalar|null> $attributes Declaration facts
     */
    public function __construct(public readonly LiteralKind $kind, public readonly int|float|string|bool|null $literal = null, public readonly array $operands = [], public readonly array $attributes = [])
    {
    }

    /**
     * Records an ordinary PHP literal.
     * @param scalar|null $value Source literal
     */
    public static function constant(int|float|string|bool|null $value): self
    {
        return new self(LiteralKind::Constant, $value);
    }

    /**
     * Records an initializer whose syntax needs evaluation.
     */
    public static function opaque(string $reason): self
    {
        return new self(LiteralKind::Unresolved, $reason);
    }

    /**
     * Captures an array literal before its elements require any evaluation.
     * @param array<array-key, self> $elements Literal elements
     */
    public static function array(array $elements): self
    {
        return new self(LiteralKind::Array, operands: $elements, attributes: ['open' => false]);
    }

    /**
     * Reads a scalar token without interpreting expressions or enum cases.
     * @return scalar|null Source literal
     * @throws LogicException If the syntax is not a scalar literal
     */
    public function native(): int|float|string|bool|null
    {
        if ($this->kind !== LiteralKind::Constant) {
            throw new LogicException('Only scalar literal syntax has a native value.');
        }
        return $this->literal;
    }
}
