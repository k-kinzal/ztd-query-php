<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement as MySql;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Statement;

/**
 * Checks explicit locks on the grant tables an account operation opens internally.
 *
 * Modern account changes open every grant table before checking their WRITE
 * locks or looking up identities. Legacy operations open their required subset. Modern SHOW CREATE USER only reads mysql.user;
 * its legacy form, SHOW GRANTS and SET ROLE use the privilege cache. Observed on native
 * MySQL 5.6 through 9.1, including partial and complete grant-table lock lists.
 *
 * @visibility MySqlMemory
 */
final class AccountTables
{
    /**
     * The grant tables opened for account creation and removal on legacy releases.
     */
    public const LEGACY = ['user', 'db', 'tables_priv', 'columns_priv', 'procs_priv', 'proxies_priv'];

    /**
     * Additional grant tables opened after the legacy set on MySQL 8.0 and later.
     */
    public const MODERN = ['role_edges', 'default_roles', 'global_grants', 'password_history'];

    /**
     * Checks all required names before checking their write permissions.
     *
     * @throws \MySqlMemory\Error\SqlError When a grant table lacks the required lock
     */
    public function check(Statement $statement, Session $session): void
    {
        $inspection = $statement instanceof MySql\Utility\Show\Server\ShowCreateUser && !$session->settings()->legacy();
        if (!$inspection && !in_array($statement::class, [
            MySql\Account\CreateUser::class, MySql\Account\AlterUser::class,
            MySql\Account\DropUser::class, MySql\Account\RenameUser::class,
            MySql\Account\SetPassword::class, MySql\Account\CreateRole::class,
            MySql\Account\DropRole::class, MySql\Account\SetDefaultRole::class,
            MySql\Account\AlterDefaultRole::class, MySql\Account\Privilege\GrantPrivileges::class,
            MySql\Account\Privilege\GrantRoles::class, MySql\Account\Privilege\GrantProxy::class,
            MySql\Account\Privilege\RevokePrivileges::class, MySql\Account\Privilege\RevokeRoles::class,
            MySql\Account\Privilege\RevokeProxy::class, MySql\Account\Privilege\RevokeAll::class,
        ], true)) {
            return;
        }
        if ($session->settings()->legacy() && ($statement instanceof MySql\Account\Privilege\GrantPrivileges || $statement instanceof MySql\Account\Privilege\RevokePrivileges)) {
            return;
        }
        $tables = $inspection ? ['user'] : self::LEGACY;
        if ($session->settings()->legacy()) {
            $tables = match (true) {
                $statement instanceof MySql\Account\SetPassword, $statement instanceof MySql\Account\AlterUser => ['user'],
                $statement instanceof MySql\Account\Privilege\GrantProxy, $statement instanceof MySql\Account\Privilege\RevokeProxy => ['user', 'proxies_priv'],
                default => $tables,
            };
        }
        if (!$inspection && !$session->settings()->legacy()) {
            array_push($tables, ...self::MODERN);
        }
        (new Locks())->required($session, array_map(static fn (string $table): QualifiedName => new QualifiedName(new Name($table), new Name('mysql')), $tables), !$inspection);
    }

    /**
     * Checks the legacy grant tables after privilege-object validation.
     *
     * @throws \MySqlMemory\Error\SqlError When a required grant table lacks its lock
     */
    public function privileges(MySql\Account\Privilege\GrantPrivileges|MySql\Account\Privilege\RevokePrivileges $statement, Session $session): void
    {
        if ($session->locks === [] || !$session->settings()->legacy()) {
            return;
        }
        $tables = match (true) {
            $statement->kind !== MySql\Account\Privilege\Level\ObjectKind::Table => ['user', 'procs_priv'],
            $statement->level instanceof MySql\Account\Privilege\Level\ObjectLevel => ['user', 'tables_priv'],
            default => ['user', 'db'],
        };
        if ($statement->kind === MySql\Account\Privilege\Level\ObjectKind::Table && $statement->level instanceof MySql\Account\Privilege\Level\ObjectLevel && ($statement instanceof MySql\Account\Privilege\RevokePrivileges || array_filter($statement->privileges, static fn ($privilege): bool => $privilege instanceof MySql\Account\Privilege\Item\StaticPrivilege && $privilege->columns !== []) !== [])) {
            $tables[] = 'columns_priv';
        }
        (new Locks())->required($session, array_map(static fn (string $table): QualifiedName => new QualifiedName(new Name($table), new Name('mysql')), $tables), true);
    }

}
