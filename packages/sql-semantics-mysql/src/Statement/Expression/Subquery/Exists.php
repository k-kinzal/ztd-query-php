<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A test for rows: `EXISTS (SELECT …)` (`Item_exists_subselect`).
 *
 * Rule: MYSQL-EXISTS-001. Facts: 1 when the subquery returns a row and 0
 * otherwise, an integer that is never NULL; the columns of the subquery do
 * not matter. Terminates: the query is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/exists-and-not-exists-subqueries.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Testing for rows
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE EXISTS (SELECT 1)');
 *     $query->facts->scalar($query->statement->where)->nullability // => \SqlSemantics\Statement\Type\Nullability::NotNull
 */
final class Exists implements Scalar
{
    use Snapshot;

    /**
     * @param Query $query The query
     */
    public function __construct(public readonly Query $query)
    {
    }

    /**
     * Derives the query; the test is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->query($this->query, $environment);

        return (new Operands())->truth(Nullability::NotNull);
    }

    /**
     * Writes EXISTS and the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXISTS')->symbol('(')->node($this->query)->symbol(')');
    }
}
