<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

/**
 * The effective decimal precision and scale enforced by a database.
 *
 * Scale may be negative or greater than precision in dialects that allow it.
 * @example Reading the typed value
 *     (new \SqlSemantics\Statement\Declaration\NumericSize(5, 0))->scale // => 0
 * @visibility public
 */
final class NumericSize
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Asserts the construction invariants of the semantic value.
     */
    public function __construct(public readonly int $precision, public readonly int $scale)
    {
        Invariant::ensure($precision > 0, 'An effective numeric precision must be positive.');
    }
}
