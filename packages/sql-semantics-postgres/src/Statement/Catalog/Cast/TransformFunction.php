<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `FROM SQL WITH FUNCTION function` or `TO SQL WITH FUNCTION function` of a transform.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createtransform.html.
 *
 * @visibility public
 * @example Reading the direction of a transform function
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TRANSFORM FOR hstore LANGUAGE plpython3u (TO SQL WITH FUNCTION f(internal))');
 *     $operation->statement->functions[0]->direction // => \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\TransformDirection::ToSql
 */
final class TransformFunction implements Clause
{
    use Snapshot;

    /**
     * @param TransformDirection $direction The direction
     * @param ObjectReference $function The function signature
     */
    public function __construct(public readonly TransformDirection $direction, public readonly ObjectReference $function)
    {
    }

    /**
     * Derives the function signature.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->function->deriveClause($derivation, $environment);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->direction->value))->keyword('WITH', 'FUNCTION')->node($this->function);
    }
}
