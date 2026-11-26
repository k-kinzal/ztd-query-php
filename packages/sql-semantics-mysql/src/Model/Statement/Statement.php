<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;

/**
 * The structure of one complete statement: the root operand of an operation.
 *
 * @visibility SqlSemantics
 */
interface Statement extends Node
{
    /**
     * Derives the context-dependent facts of every part of the statement.
     */
    public function deriveStatement(Derivation $derivation): void;
}
