<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;

/**
 * A row-producing query body: a selection, a row list, a set operation, and so on.
 *
 * A query nested in another statement may refer to the enclosing query; it is
 * not a statement root of its own.
 *
 * @visibility public
 * @example Reading the output fields of a query
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS one');
 *     $operation->field('one')->position // => 0
 */
interface Query extends Node
{
    /**
     * Derives the output of this query inside the environment of its use position.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact;
}
