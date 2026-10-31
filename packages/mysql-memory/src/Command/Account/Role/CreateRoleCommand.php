<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Role;

use LogicException;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Command\Command;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\CreateRole;
use SqlSemantics\Statement\AnalyzedStatement;

/**
 * Executes complete CREATE ROLE requests through the account catalog.
 *
 * Intrinsic contradictions remain requests until execution. Ordered account
 * lookup, atomic creation and implicit commit use the same path for every request.
 *
 * @visibility MySqlMemory
 */
final class CreateRoleCommand implements Command
{
    /**
     * Answers true.
     */
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Creates roles after the common lifecycle has checked the request's access.
     *
     * @throws LogicException When dispatch supplies another semantic command
     */
    public function execute(AnalyzedStatement $statement, Session $session, Context $context, Connection $connection): Reply
    {
        if (!$statement instanceof CreateRole) {
            throw new LogicException('CREATE ROLE requires its concrete semantic statement.');
        }
        $session->transaction->commit();
        return (new Definitions())->execute($statement, $session, $context);
    }

    /**
     * Checks name domains and initial operand notices without inspecting account state.
     *
     * @param bool $notices Whether this is the initial reading of the operands
     */
    public function prepare(CreateRole $statement, Session $session, bool $notices = true): void
    {
        (new Names($statement->profile->grammar))->read($statement->roles, $session, $notices);
    }
}
