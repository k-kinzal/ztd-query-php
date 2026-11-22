<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Sublinks;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

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
 * @visibility SqlSemantics
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
