<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Construction\Derivation;

/**
 * The structure of one complete statement: the root operand of an operation.
 *
 * @visibility public
 * @example Telling the analyzed statement apart from the operation that binds it
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1');
 *     $operation->statement instanceof \SqlSemantics\Statement\Statement // => true
 */
interface Statement extends Node
{
    /**
     * Derives the context-dependent facts of every part of the statement.
     */
    public function deriveStatement(Derivation $derivation): void;
}
