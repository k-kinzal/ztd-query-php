<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Statement\Fact\Facts;

/**
 * A retained index instruction and its bound dependencies, without an ordinary Operation.
 *
 * @visibility MySqlMemory
 */
final class IndexPreparation
{
    /**
     * @param CreateIndex|AlterTable $statement The complete retained instruction
     * @param Facts $facts Its dependencies in the current declaration context
     */
    public function __construct(public readonly CreateIndex|AlterTable $statement, public readonly Facts $facts)
    {
    }
}
