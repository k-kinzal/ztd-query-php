<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotImplemented;

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
 * @visibility SqlSemantics
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
