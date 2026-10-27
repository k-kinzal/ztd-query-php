<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;

/**
 * A retained index instruction and the conditions of its bound dependencies.
 *
 * @visibility MySqlMemory
 */
final class IndexPreparation
{
    /**
     * @param CreateIndex|AlterTable $statement The complete retained instruction
     * @param StatementSemantics $facts Its dependencies in the current declaration context
     */
    public function __construct(public readonly CreateIndex|AlterTable $statement, public readonly StatementSemantics $facts)
    {
    }
}
