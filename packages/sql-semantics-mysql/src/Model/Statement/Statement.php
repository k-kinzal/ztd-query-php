<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;

/**
 * The structure of one complete statement: the root operand of an operation.
 *
 * @visibility public
 * @example Telling the analyzed statement apart from the operation that binds it
 *     $operation = (new \SqlSemantics\Platform\MySql\Semantics())->analyze('SELECT 1');
 *     $operation->statement instanceof \SqlSemantics\Platform\MySql\Model\Statement\Statement // => true
 */
interface Statement extends Node
{
    /**
     * Derives the context-dependent facts of every part of the statement.
     */
    public function deriveStatement(Derivation $derivation): void;
}
