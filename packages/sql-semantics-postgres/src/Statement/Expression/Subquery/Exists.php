<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A test of whether a query returns a row: `EXISTS (SELECT …)`.
 *
 * Mirrors PostgreSQL's `SubLink` node of kind `EXISTS_SUBLINK`.
 *
 * Rule: PG-EXISTS-001. The query is derived in the environment of the
 * expression. Facts: `boolean`, never NULL; an unaliased result column is
 * named `exists`.
 * Source: https://www.postgresql.org/docs/17/functions-subquery.html#FUNCTIONS-SUBQUERY-EXISTS. Status: Implemented.
 *
 * @visibility public
 * @example Naming an EXISTS column
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT EXISTS (SELECT 1)')->field(0)->name->value // => 'exists'
 */
final class Exists implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Query $query The query inside the parentheses
     */
    public function __construct(public readonly Query $query)
    {
    }

    /**
     * Names an unaliased result column `exists`.
     */
    public function outputName(): Name
    {
        return new Name('exists');
    }

    /**
     * Derives the query; the test is a boolean that is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->query($this->query, $environment);

        return new ScalarFact(new Known(Builtin::Bool), Nullability::NotNull);
    }

    /**
     * Writes EXISTS and the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXISTS')->symbol('(')->node($this->query)->symbol(')');
    }
}
