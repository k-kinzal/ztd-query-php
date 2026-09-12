<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Upsert;

/**
 * Preserves a literal supplied through the public expression API without coercion.
 */
interface UpsertLiteralSource
{
    /**
     * Returns the original literal, including caller-defined PHP values.
     */
    public function value(): mixed;
}
