<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Subquery;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Query;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;

/**
 * A test for rows: `EXISTS (SELECT …)` (`Item_exists_subselect`).
 *
 * Rule: MYSQL-EXISTS-001. Facts: 1 when the subquery returns a row and 0
 * otherwise, an integer that is never NULL; the columns of the subquery do
 * not matter. Terminates: the query is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/exists-and-not-exists-subqueries.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
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
