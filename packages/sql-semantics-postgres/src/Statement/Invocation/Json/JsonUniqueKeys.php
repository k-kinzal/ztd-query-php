<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `WITH UNIQUE [KEYS]` or `WITHOUT UNIQUE [KEYS]`: whether duplicate object keys are rejected.
 *
 * The optional KEYS word is noise. Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE,
 * https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-MISC.
 *
 * @visibility public
 * @example Reading a uniqueness requirement
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys(true))->unique // => true
 */
final class JsonUniqueKeys implements Clause
{
    use Snapshot;

    /**
     * @param bool $unique Whether WITH UNIQUE is written, rather than WITHOUT UNIQUE
     */
    public function __construct(public readonly bool $unique)
    {
    }

    /**
     * Derives nothing: the clause holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->unique ? 'WITH' : 'WITHOUT', 'UNIQUE', 'KEYS');
    }
}
