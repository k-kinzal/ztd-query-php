<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Account\DropRole;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;
use SqlSemantics\Statement\Operation;

/**
 * Executes DROP USER and DROP ROLE.
 *
 * The statement commits the open transaction. An account that does not exist fails the
 * statement (ER_CANNOT_USER, naming every such account) and nothing is dropped, or with IF
 * EXISTS draws a note. Dropping an account revokes it from the accounts it was granted to as a
 * role and from their default roles; the account of the session may be dropped (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-user.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-role.html.
 *
 * @visibility MySqlMemory
 */
final class DropUserCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Drops the accounts.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof DropUser || $statement instanceof DropRole);
        $session->transaction->commit();
        $names = new Names();
        $listed = $statement instanceof DropUser ? $statement->users : $statement->roles;
        $names->check($listed);
        $accounts = $session->instance->accounts;
        $missing = [];
        $dropped = [];
        foreach ($listed as $name) {
            $identity = $names->identity($name, $session);
            $names->ascii($identity, 7, $context->diagnostics);
            if ($accounts->find($identity) === null || isset($dropped[$identity->key()])) {
                $missing[] = $identity;
                if ($statement->ifExists) {
                    $context->diagnostics->note(ErrorCode::UserDoesNotExist, ErrorCode::UserDoesNotExist->message($identity->quoted()));
                }
                continue;
            }
            $dropped[$identity->key()] = $identity;
        }
        if ($missing !== [] && !$statement->ifExists) {
            $operation = $statement instanceof DropUser ? 'DROP USER' : 'DROP ROLE';
            throw ErrorCode::CannotUser->error($operation, implode(',', array_map(static fn (Identity $identity): string => $identity->quoted(), $missing)));
        }
        foreach ($dropped as $identity) {
            $accounts->drop($identity);
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }
}
