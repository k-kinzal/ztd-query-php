<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Role;

use LogicException;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Command\Command;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\AnalyzedStatement;

/**
 * Executes role grants from complete role and recipient operands.
 *
 * The common lifecycle owns access and diagnostics; account execution owns
 * current identities, ordered role lookup, cycles and atomic edge changes.
 *
 * @visibility MySqlMemory
 */
final class GrantRolesCommand implements Command
{
    /**
     * Answers true.
     */
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Grants role edges after the common lifecycle has checked access.
     *
     * @throws LogicException When dispatch supplies another semantic command
     */
    public function execute(AnalyzedStatement $statement, Session $session, Context $context, Connection $connection): Reply
    {
        if (!$statement instanceof GrantRoles) {
            throw new LogicException('GRANT roles requires its concrete semantic statement.');
        }
        $session->transaction->commit();
        (new Grants())->apply($statement, $session);
        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Checks name domains and initial operand notices without looking up accounts.
     *
     * @param bool $notices Whether this is the initial reading of the operands
     */
    public function prepare(GrantRoles $statement, Session $session, bool $notices = true): void
    {
        $roles = array_map(static fn (GrantedRole $role): AccountName => $role->role, $statement->roles);
        (new Names($statement->profile->grammar))->read([...$roles, ...$statement->users], $session, $notices);
    }
}
