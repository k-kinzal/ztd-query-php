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
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Statement\Operation;

/**
 * Executes RENAME USER.
 *
 * The statement commits the open transaction. The pairs are renamed in order, so a later pair
 * may rename the account an earlier one named. A pair whose account does not exist, or whose
 * new name does, fails the statement (ER_CANNOT_USER, naming the account of every such pair)
 * and nothing is renamed. No pair may name an account granted to another as a role, before
 * any pair is renamed (ER_RENAME_ROLE). The privileges, roles and default roles of the account
 * keep with it (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-user.html.
 *
 * @visibility MySqlMemory
 */
final class RenameUserCommand implements Command
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
     * Renames the accounts.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof RenameUser);
        $session->transaction->commit();
        $names = new Names();
        $listed = [];
        foreach ($statement->renamings as $renaming) {
            $listed[] = $renaming->from;
            $listed[] = $renaming->to;
        }
        $names->check($listed);
        $accounts = $session->instance->accounts;
        $pairs = [];
        foreach ($statement->renamings as $renaming) {
            $pairs[] = [$names->identity($renaming->from, $session), $names->identity($renaming->to, $session)];
        }
        foreach ($pairs as [$from, $to]) {
            if ($accounts->granted($from) || $accounts->granted($to)) {
                throw ErrorCode::RenameRole->error();
            }
        }
        $saved = $accounts->copy();
        $failed = [];
        foreach ($pairs as [$from, $to]) {
            if ($accounts->find($from) === null || $accounts->find($to) !== null) {
                $failed[] = $from;
                continue;
            }
            $accounts->rename($from, $to);
        }
        if ($failed !== []) {
            $accounts->restore($saved);
            throw ErrorCode::CannotUser->error('RENAME USER', implode(',', array_map(static fn (Identity $identity): string => $identity->quoted(), $failed)));
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }
}
