<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredAlterTable;
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
     * @param DeferredCreateIndex|DeferredAlterTable $statement The complete retained instruction
     * @param Facts $facts Its dependencies in the current declaration context
     */
    public function __construct(public readonly DeferredCreateIndex|DeferredAlterTable $statement, public readonly Facts $facts)
    {
    }
}
