<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Facts;

/**
 * A retained creation instruction bound for the ordinary definition consumer.
 *
 * This is consumer preparation data, not a semantic Operation. Its declaration
 * and facts are derived from the same complete instruction without source SQL.
 *
 * @visibility MySqlMemory
 */
final class TablePreparation
{
    /**
     * @param DeferredCreateTable $statement The complete retained creation instruction
     * @param Facts $facts The facts derived in the current database state
     * @param Table $table The declaration supplied by that instruction
     */
    public function __construct(public readonly DeferredCreateTable $statement, public readonly Facts $facts, public readonly Table $table)
    {
    }

    /**
     * Answers the instruction's declaration in the definition consumer's order.
     *
     * @return array{Table}
     */
    public function declarations(): array
    {
        return [$this->table];
    }
}
