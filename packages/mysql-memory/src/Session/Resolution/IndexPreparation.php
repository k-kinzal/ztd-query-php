<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex;
use SqlSemantics\Statement\Fact\Facts;

/**
 * A retained index instruction and its bound dependencies, without an ordinary Operation.
 *
 * @visibility MySqlMemory
 */
final class IndexPreparation
{
    /**
     * @param DeferredCreateIndex|AlterTable $statement The complete retained instruction
     * @param Facts $facts Its dependencies in the current declaration context
     */
    public function __construct(public readonly DeferredCreateIndex|AlterTable $statement, public readonly Facts $facts)
    {
    }
}
