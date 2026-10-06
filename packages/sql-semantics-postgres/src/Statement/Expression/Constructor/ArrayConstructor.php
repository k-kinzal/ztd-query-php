<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\ArrayTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

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
 * @visibility public
 * @example Typing an array of mixed numbers
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT ARRAY[1, 2.5]')->field(0)->type->descriptor->name() // => 'numeric[]'
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
