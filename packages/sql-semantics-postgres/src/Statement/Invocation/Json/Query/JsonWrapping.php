<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The wrapper clause of JSON_QUERY or of a JSON_TABLE column.
 *
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING.
 *
 * @visibility public
 * @example Reading a wrapper clause
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapping(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapperKind::Unconditional))->kind->wraps() // => true
 */
final class JsonWrapping implements Clause
{
    use Snapshot;

    /**
     * @param JsonWrapperKind $kind The wrapping written
     */
    public function __construct(public readonly JsonWrapperKind $kind)
    {
    }

    /**
     * Derives nothing: the clause holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the wrapper clause.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->kind->value))->keyword('WRAPPER');
    }
}
