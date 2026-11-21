<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;

/**
 * The structure of one complete statement: the root operand of an operation.
 *
 * @visibility public
 * @example Telling the analyzed statement apart from the operation that binds it
 *     $operation = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('SELECT 1');
 *     $operation->statement instanceof \SqlSemantics\Platform\Sqlite\Model\Statement\Statement // => true
 */
interface Statement extends Node
{
    /**
     * Derives the context-dependent facts of every part of the statement.
     */
    public function deriveStatement(Derivation $derivation): void;
}
