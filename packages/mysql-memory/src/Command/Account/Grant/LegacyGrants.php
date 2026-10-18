<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Grant;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\GrantCommand;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Statement\Operation;

/**
 * Processes legacy grant recipients in order, retaining partial grants and all conditions.
 *
 * Missing accounts and invalid database privileges are reported per occurrence;
 * the first condition supplies the final error. Observed through MySQL 5.7.44 SQL.
 *
 * @visibility MySqlMemory
 */
final class LegacyGrants
{
    /**
     * Resolves and applies each recipient before advancing to the next.
     *
     * @param list<Identity> $identities The recipients in written order
     * @param array{string, string, string} $target The resolved privilege level
     * @throws SqlError When any recipient fails
     */
    public function execute(GrantPrivileges $statement, Operation $operation, array $identities, array $target, Session $session): void
    {
        $command = new GrantCommand();
        $first = null;
        foreach ($identities as $index => $identity) {
            try {
                $single = new GrantPrivileges($statement->privileges, $statement->kind, $statement->level, [$statement->grantees[$index]], $statement->tls, $statement->options, $statement->as);
                $account = $command->accounts($single, [$identity], $session)[0];
                $command->apply($statement, $operation, $account, $target, $session);
            } catch (SqlError $error) {
                if (!in_array($error->error, [AccountError::PasswordNoMatch, StatementError::WrongUsage], true)) {
                    throw $error;
                }
                $session->diagnostics->error($error->getCode(), $error->getMessage());
                $first ??= $error;
            }
        }
        if ($first !== null) {
            throw new SqlError($first->error, $first->getMessage(), $first, recorded: true);
        }
    }
}
