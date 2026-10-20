<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AccountError;
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
 * and modern releases restore the accounts; legacy releases keep successful renames.
 * Modern renames of the executing account update this connection's CURRENT_USER immediately,
 * including before a later error, while USER() and other connections stay unchanged.
 * No pair may name an account granted to another as a role, before
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
        return $this->apply($statement, $session, $context);
    }

    /**
     * Applies a complete ordered identity request, including partial legacy effects.
     */
    public function apply(RenameUser|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRenameUser $statement, Session $session, Context $context): Reply
    {
        $session->transaction->commit();
        $names = new Names($session->settings()->release());
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
                throw AccountError::RenameRole->error();
            }
        }
        $saved = $accounts->copy();
        $failed = [];
        foreach ($statement->renamings as $renaming) {
            $from = $names->identity($renaming->from, $session);
            $to = $names->identity($renaming->to, $session);
            if (!$this->rename($from, $to, $session)) {
                $failed[] = $from;
                continue;
            }
            if (!$session->settings()->legacy() && $session->variables->definer->key() === $from->key()) {
                $session->variables->definer = $to;
            }
        }
        if ($failed !== []) {
            if (!$session->settings()->legacy()) {
                $accounts->restore($saved);
                $accounts->reload();
            }
            throw (new Names($session->settings()->release()))->failure('RENAME USER', implode(',', array_map(static fn (Identity $identity): string => $identity->quoted(), $failed)));
        }

        return new Completion(0, 0, $context->diagnostics->raised());
    }
    /**
     * Checks destination grant rows before reading and rewriting the source rows.
     *
     * ASCII conversion warnings follow the grant-table accesses, including a
     * failed rename. A destination found in mysql.user stops the lookup after
     * that table. Observed on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
     */
    public function rename(Identity $from, Identity $to, Session $session): bool
    {
        $accounts = $session->instance->accounts;
        $names = new Names($session->settings()->release());
        $target = $session->settings()->legacy() ? $accounts->find($to) : $accounts->stored($to->stored());
        $names->ascii($to, $target === null ? 7 : 1, $session->diagnostics);
        if ($target !== null) {
            return false;
        }
        $source = $accounts->find($from);
        $stored = $session->settings()->legacy() ? $source : $accounts->stored($from->stored());
        $this->conversions($from, $to, $stored, $session);
        if ($source === null && $stored === null) {
            return false;
        }
        if ($stored !== null && !$session->settings()->legacy()) {
            $stored->storedName = $to->stored();
        }
        $accounts->rename($from, $to);
        return true;
    }

    /**
     * Preserves lookup and write warning order for all seven grant-table phases.
     * A Unicode source keeps its routine and proxy cache grants without writing
     * those stored rows during rename, as observed through native SQL histories.
     */
    public function conversions(Identity $from, Identity $to, ?\MySqlMemory\Account\Account $account, Session $session): void
    {
        $names = new Names($session->settings()->release());
        $grants = $account?->grants;
        $ascii = $from->key() === $from->stored()->key();
        $writes = [$account === null ? 0 : 1, count($grants->databases ?? []), $ascii ? count($grants->routines ?? []) : 0,
            count($grants->tables ?? []), array_sum(array_map(static fn (array $table): int => count($table[2]->columns), $grants->tables ?? [])),
            $ascii ? count($grants->proxies ?? []) : 0, 0];
        foreach ($writes as $count) {
            $names->ascii($from, 1, $session->diagnostics);
            $names->ascii($to, $count, $session->diagnostics);
        }
        $names->ascii($from, count($grants->dynamic ?? []), $session->diagnostics, 'HOST');
        $names->ascii($to, count($grants->dynamic ?? []), $session->diagnostics, 'HOST');
    }

}
