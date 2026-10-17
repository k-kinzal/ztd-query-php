<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use Fuzz\Structure\Execution;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Replays the native fixture's two SET requests with directly constructed operands.
 * Assigning variable storage alone would omit the observable request history.
 */
final class SessionSetup
{
    /**
     * Sets connection encoding, mode, collation and clock without submitting SQL to memory.
     */
    public function apply(Session $session, string $mode): void
    {
        $execution = new Execution();
        $execution->runIn($session, new SetVariables([new SetNames(new CharsetName(new Name('utf8mb4')))]));
        $execution->runIn($session, new SetVariables([
            new NameAssignment(new Name('sql_mode'), new StringLiteral([$mode]), VariableScope::Session),
            new NameAssignment(new Name('collation_connection'), new StringLiteral(['utf8mb4_bin'])),
            new NameAssignment(new Name('timestamp'), new NumberLiteral('1700000000')),
        ]));
    }
}
