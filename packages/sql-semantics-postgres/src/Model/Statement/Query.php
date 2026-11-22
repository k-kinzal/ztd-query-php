<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;

/**
 * A row-producing query body: a selection, a row list, a set operation, and so on.
 *
 * A query nested in another statement may refer to the enclosing query; it is
 * not a statement root of its own.
 *
 * @visibility SqlSemantics
 */
interface Query extends Node
{
    /**
     * Derives the output of this query inside the environment of its use position.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact;
}
