<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `KEEP QUOTES` or `OMIT QUOTES` of JSON_QUERY or a JSON_TABLE column; the words ON SCALAR STRING are noise.
 *
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING.
 *
 * @visibility public
 * @example Reading a quotes clause
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQuoting(false))->keep // => false
 */
final class JsonQuoting implements Clause
{
    use Snapshot;

    /**
     * @param bool $keep Whether KEEP QUOTES is written, rather than OMIT QUOTES
     */
    public function __construct(public readonly bool $keep)
    {
    }

    /**
     * Derives nothing: the clause holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the quotes clause.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->keep ? 'KEEP' : 'OMIT', 'QUOTES');
    }
}
