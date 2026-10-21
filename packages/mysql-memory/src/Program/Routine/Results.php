<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Routine;

use SqlSemantics\Platform\MySql\Rules\Routine\ProgramRestrictions;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Execute;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Node;

/**
 * Finds the result notification a procedure body declares, including unreachable instructions.
 *
 * SHOW PROFILE and SHOW PROFILES emit results without declaring them. A procedure
 * containing only these statements omits the continuation flag before its final
 * completion; an ordinary SELECT in an unexecuted branch restores it. CALL alone
 * does not declare results; the called body supplies its own notification while
 * it runs. Verified through text and binary protocol observations on MySQL.
 *
 * @visibility MySqlMemory
 */
final class Results
{
    /**
     * Answers whether a body declares result-producing instructions.
     */
    public function announced(Node $body): bool
    {
        $pending = [$body];
        while ($pending !== []) {
            $part = array_pop($pending);
            if (is_array($part)) {
                array_push($pending, ...array_values($part));
            } elseif ($part instanceof Statement) {
                if ($part instanceof Execute || (new ProgramRestrictions())->results($part)) {
                    return true;
                }
            } elseif ($part instanceof Node && !$part instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration
                && !$part instanceof \SqlSemantics\Statement\Scalar && !$part instanceof \SqlSemantics\Statement\Query) {
                array_push($pending, ...array_values(get_object_vars($part)));
            }
        }

        return false;
    }
}
