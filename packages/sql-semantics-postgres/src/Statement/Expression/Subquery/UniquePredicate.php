<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotImplemented;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The SQL-standard test of whether a query returns no duplicate rows: `UNIQUE [NULLS [NOT] DISTINCT] (SELECT …)`.
 *
 * The grammar accepts the predicate, and the server rejects it while parsing
 * because it does not implement it.
 *
 * Rule: PG-UNIQUE-PREDICATE-001. The query is derived in the environment of
 * the expression. Facts: the predicate is reported as not implemented, and
 * its type is invalid.
 * Source: https://www.postgresql.org/docs/17/features.html (feature F291), the grammar action of `a_expr: UNIQUE …`. Status: Implemented.
 *
 * @visibility public
 * @example Building the predicate over a query
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1')->statement;
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\UniquePredicate(false, $query))->nullsDistinct // => false
 */
final class UniquePredicate implements Scalar
{
    use Snapshot;

    /**
     * @param bool|null $nullsDistinct Whether NULLS DISTINCT (true) or NULLS NOT DISTINCT (false) is written, or null when neither is
     * @param Query $query The query inside the parentheses
     */
    public function __construct(public readonly ?bool $nullsDistinct, public readonly Query $query)
    {
    }

    /**
     * Derives the query and reports the predicate.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->query($this->query, $environment);
        $problem = new NotImplemented('UNIQUE predicate');
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), Nullability::Dependent);
    }

    /**
     * Writes UNIQUE, the NULLS treatment and the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('UNIQUE');
        if ($this->nullsDistinct !== null) {
            $out->keyword(...($this->nullsDistinct ? ['NULLS', 'DISTINCT'] : ['NULLS', 'NOT', 'DISTINCT']));
        }
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
