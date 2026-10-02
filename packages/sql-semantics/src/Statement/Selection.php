<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * A statement or query that reads rows from an input relation structure.
 *
 * @visibility public
 * @example Reading the input relation of a selection
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t JOIN u');
 *     $query->statement instanceof \SqlSemantics\Statement\Selection // => true
 */
interface Selection extends Node
{
    /**
     * Answers the input relation structure, or null when the selection has no input.
     */
    public function input(): ?Relation;
}
