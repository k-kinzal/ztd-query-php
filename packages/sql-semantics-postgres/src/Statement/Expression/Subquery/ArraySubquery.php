<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Sublinks;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * An array of the values a query returns: `ARRAY(SELECT …)`.
 *
 * Mirrors PostgreSQL's `SubLink` node of kind `ARRAY_SUBLINK`.
 *
 * Rule: PG-ARRAY-SUBQUERY-001. The query is derived in the environment of
 * the expression and must return one column. Facts: an array of the
 * column's type (an array column gives an array of one more dimension, which
 * has the same type); never NULL, since no row gives an empty array; an
 * unaliased result column is named `array`.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-ARRAY-CONSTRUCTORS. Status: Implemented.
 *
 * @visibility public
 * @example Typing an array subquery
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT ARRAY(SELECT 1)')->field(0)->type->descriptor->name() // => 'integer[]'
 */
final class ArraySubquery implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Query $query The query inside the parentheses
     */
    public function __construct(public readonly Query $query)
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
     * Derives the query and the array type of its column.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->query($this->query, $environment);

        return new ScalarFact((new Sublinks())->array($derivation, $fact), Nullability::NotNull);
    }

    /**
     * Writes ARRAY and the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('ARRAY')->symbol('(')->node($this->query)->symbol(')');
    }
}
