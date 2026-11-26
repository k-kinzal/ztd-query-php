<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\ArrayTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

/**
 * An array built from values: `ARRAY[a, b]`, `ARRAY[[a, b], [c, d]]`.
 *
 * Mirrors PostgreSQL's `A_ArrayExpr` node.
 *
 * Rule: PG-ARRAY-CONSTRUCTOR-001. Facts: an array of the common type of the
 * values (PG-UNIFICATION-001; unknown-typed values give `text`); values that
 * are arrays, and sub-arrays, make a multidimensional array of the same
 * type; an empty array has no element type of its own, which a cast
 * supplies; the array is never NULL. An unaliased result column is named
 * `array`. Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-ARRAY-CONSTRUCTORS. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ArrayConstructor implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param ArrayItems $items The bracketed items
     */
    public function __construct(public readonly ArrayItems $items)
    {
    }

    /**
     * Names an unaliased result column `array`.
     */
    public function outputName(): Name
    {
        return new Name('array');
    }

    /**
     * Derives every value and the array type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact((new ArrayTyping())->items($derivation, $environment, $this->items), Nullability::NotNull);
    }

    /**
     * Writes ARRAY and the items.
     */
    public function render(Output $out): void
    {
        $out->keyword('ARRAY')->node($this->items);
    }
}
