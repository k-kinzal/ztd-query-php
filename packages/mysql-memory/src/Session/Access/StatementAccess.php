<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Statement\Node;

/**
 * Checks connection restrictions and definition options before object resolution.
 *
 * @visibility MySqlMemory
 */
final class StatementAccess
{
    /**
     * Checks connection restrictions and table-creation options before resolving tables and columns.
     *
     * @throws SqlError When the connection or CREATE TABLE options prohibit the statement
     */
    public function check(Node $statement, Session $session, Context $context): void
    {
        if (!$statement instanceof \SqlSemantics\Statement\Statement) {
            (new PasswordAccess())->check($statement, $session);
            return;
        }
        if (($statement instanceof \SqlSemantics\Platform\MySql\Statement\View\CreateView || $statement instanceof \SqlSemantics\Platform\MySql\Statement\View\AlterView) && $session->locks !== []) {
            (new PasswordAccess())->check($statement, $session);
            throw \MySqlMemory\Error\Family\StatementError::LockedOrActiveTransaction->error();
        }
        if (Stages::definitionCondition($statement)) {
            (new PasswordAccess())->check($statement, $session);
            (new TableCreation())->check($statement, $session);
            return;
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction) {
            (new \MySqlMemory\Command\Program\RoutineCommand())->types($statement, $session, $context);
        }
        (new \MySqlMemory\Command\Definition\StorageOptions())->resolve($statement, $session, $context);
        (new PasswordAccess())->check($statement, $session);
        (new TableCreation())->check($statement, $session);
        (new TableCreation())->validate($statement, $session);
        (new \MySqlMemory\Command\Definition\Constraint\SpatialColumns())->prepare($statement, $session);
        (new \MySqlMemory\Command\Definition\Constraint\IndexOptions())->prepare($statement, $session, $context);
    }

}
