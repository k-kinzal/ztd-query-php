<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Upsert;

/**
 * Retains the type and identity of one expression literal.
 *
 * @template TValue
 */
final class UpsertLiteral implements UpsertLiteralSource
{
    /**
     * @param TValue $value Literal supplied by the caller.
     */
    public function __construct(private readonly mixed $value)
    {
    }

    /**
     * @return TValue The unchanged literal.
     */
    public function value(): mixed
    {
        return $this->value;
    }
}
