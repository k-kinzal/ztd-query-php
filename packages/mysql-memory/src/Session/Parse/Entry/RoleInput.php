<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;

/**
 * Formats role-list input diagnostics from decoded operands and positions.
 *
 * Input text is used only for the diagnostic excerpt and never enters a plan.
 *
 * @visibility MySqlMemory
 */
final class RoleInput
{
    /**
     * Formats a role-list input refusal using its position, without requiring a semantic statement.
     */
    public function error(\SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch $problem, string $text): SqlError
    {
        $near = $problem->offset === null ? (new \MySqlMemory\Command\Account\Levels())->near($text, $problem->item) : substr($text, $problem->offset);
        $line = $problem->offset === null ? 1 : substr_count(substr($text, 0, $problem->offset), "\n") + 1;

        return new SqlError(StatementError::ParseError, ($problem->roleExpected ? 'Illegal authorization identifier' : 'Illegal privilege identifier') . " near '" . mb_strcut($near, 0, 80, 'UTF-8') . "' at line " . $line);
    }

}
