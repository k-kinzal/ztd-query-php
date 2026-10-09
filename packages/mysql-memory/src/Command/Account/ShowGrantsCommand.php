<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Accounts;
use MySqlMemory\Account\Grants;
use MySqlMemory\Account\GrantText;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowGrants;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW GRANTS.
 *
 * The statement writes the GRANT statements of an account (see GrantText), in one text column
 * of 4096 characters named after the account, 1024 in MySQL 5.6 and 5.7 (verified on live
 * 5.6.51 and 5.7.44 servers). Without FOR, or FOR CURRENT_USER, it shows the
 * account of the session with the privileges of its active roles; USING adds the privileges of
 * roles granted to the account, and of the roles granted to them. An account that does not
 * exist is ER_NONEXISTING_GRANT, and a role USING names that is not granted to the account is
 * ER_ROLE_NOT_GRANTED (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-grants.html.
 *
 * @visibility MySqlMemory
 */
final class ShowGrantsCommand implements Command
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
     * Lists the grants.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowGrants);
        $names = new Names($session->settings()->release());
        $names->check([$statement->user, ...$statement->using]);
        $current = !$statement->user instanceof AccountName;
        $identity = $statement->user === null ? new Identity($session->user, '%') : $names->identity($statement->user, $session);
        $accounts = $session->instance->accounts;
        $account = $accounts->find($identity);
        if ($account === null) {
            throw AccountError::NonexistingGrant->error($identity->user, $identity->host);
        }
        $granted = $accounts->roles($identity);
        $roles = $current ? $session->variables->roles : [];
        foreach ($statement->using as $role) {
            $using = $role instanceof \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser ? new Identity('(null)', '(null)') : $names->identity($role, $session);
            if (!isset($granted[$using->key()])) {
                throw AccountError::RoleNotGranted->error($using->backquoted(), $identity->backquoted());
            }
            $roles[] = $using;
        }
        $grants = $account->grants->copy();
        $grants->merge($this->through($roles, $accounts));
        $grants->prune();
        $release = $session->settings()->release();
        $legacy = (new \MySqlMemory\Account\Catalog($release))->legacy();
        $column = new ResultColumn('Grants for ' . $identity->text(), Field::VarString, $legacy ? 1024 : 4096, 31, ColumnFlag::NotNull->value, 255);
        $lines = (new GrantText())->lines($identity, $grants, $granted, $release, $account);

        return new ResultSet([$column], array_map(static fn (string $line): array => [$line], $lines), $context->diagnostics->count());
    }

    /**
     * Answers the privileges of roles and of the roles granted to them.
     *
     * @param list<Identity> $roles
     */
    public function through(array $roles, Accounts $accounts): Grants
    {
        $grants = new Grants();
        $seen = [];
        while ($roles !== []) {
            $role = array_pop($roles);
            if (isset($seen[$role->key()])) {
                continue;
            }
            $seen[$role->key()] = true;
            $account = $accounts->find($role);
            if ($account === null) {
                continue;
            }
            $grants->merge($account->grants);
            foreach ($accounts->roles($role) as [$next]) {
                $roles[] = $next;
            }
        }

        return $grants;
    }
}
