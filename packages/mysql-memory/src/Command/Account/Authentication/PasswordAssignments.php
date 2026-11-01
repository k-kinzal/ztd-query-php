<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Authentication;

use Closure;
use MySqlMemory\Error\SqlError;

/**
 * Applies the MySQL 5.6 SET list, including passwords, in update order.
 * A refused password does not undo other assignments or stop later updates.
 * The first error fails the statement; subsequent errors remain diagnostics.
 * Verified with native password, user-variable and charset assignments.
 *
 * @visibility MySqlMemory
 */
final class PasswordAssignments
{
    /**
     * @param list<Closure> $actions Resolved assignment updates
     * @throws SqlError When one or more updates fail
     */
    public function apply(array $actions): void
    {
        $first = null;
        $following = [];
        foreach ($actions as $action) {
            try {
                $action();
            } catch (SqlError $error) {
                if ($first === null) {
                    $first = $error;
                } else {
                    $following[] = [$error->getCode(), $error->getMessage()];
                }
            }
        }
        if ($first !== null) {
            throw new SqlError($first->error, $first->getMessage(), $first, $following);
        }
    }
}
