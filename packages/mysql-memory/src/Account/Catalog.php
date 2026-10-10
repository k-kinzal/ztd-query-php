<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

use SqlSemantics\Contract\GrammarRelease;

/**
 * The privileges the server knows: the static privileges in the order it writes them, the levels each fits, and the dynamic privileges it registers.
 *
 * The dynamic privileges are those a server without plugins or components registers (verified
 * on a live 8.4 server). MySQL 5.6 and 5.7 have neither CREATE ROLE and DROP ROLE nor dynamic
 * privileges (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/privileges-provided.html,
 * https://dev.mysql.com/doc/refman/5.7/en/privileges-provided.html.
 *
 * @visibility MySqlMemory
 */
final class Catalog
{
    /**
     * The static privileges, in the order SHOW GRANTS writes them.
     */
    public const STATIC = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'RELOAD', 'SHUTDOWN', 'PROCESS', 'FILE', 'REFERENCES', 'INDEX', 'ALTER',
        'SHOW DATABASES', 'SUPER', 'CREATE TEMPORARY TABLES', 'LOCK TABLES', 'EXECUTE', 'REPLICATION SLAVE', 'REPLICATION CLIENT', 'CREATE VIEW',
        'SHOW VIEW', 'CREATE ROUTINE', 'ALTER ROUTINE', 'CREATE USER', 'EVENT', 'TRIGGER', 'CREATE TABLESPACE', 'CREATE ROLE', 'DROP ROLE',
    ];

    /**
     * The static privileges a database grant holds, in order.
     */
    public const DATABASE = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'REFERENCES', 'INDEX', 'ALTER', 'CREATE TEMPORARY TABLES', 'LOCK TABLES',
        'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE', 'ALTER ROUTINE', 'EVENT', 'TRIGGER',
    ];

    /**
     * The static privileges a table grant holds, in order.
     */
    public const TABLE = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'REFERENCES', 'INDEX', 'ALTER', 'CREATE VIEW', 'SHOW VIEW', 'TRIGGER'];

    /**
     * The dynamic privileges the server registers, in name order.
     */
    public const DYNAMIC = [
        'ALLOW_NONEXISTENT_DEFINER', 'APPLICATION_PASSWORD_ADMIN', 'AUDIT_ABORT_EXEMPT', 'AUDIT_ADMIN', 'AUTHENTICATION_POLICY_ADMIN', 'BACKUP_ADMIN',
        'BINLOG_ADMIN', 'BINLOG_ENCRYPTION_ADMIN', 'CLONE_ADMIN', 'CONNECTION_ADMIN', 'ENCRYPTION_KEY_ADMIN', 'FIREWALL_EXEMPT', 'FLUSH_OPTIMIZER_COSTS',
        'FLUSH_PRIVILEGES', 'FLUSH_STATUS', 'FLUSH_TABLES', 'FLUSH_USER_RESOURCES', 'GROUP_REPLICATION_ADMIN', 'GROUP_REPLICATION_STREAM',
        'INNODB_REDO_LOG_ARCHIVE', 'INNODB_REDO_LOG_ENABLE', 'OPTIMIZE_LOCAL_TABLE', 'PASSWORDLESS_USER_ADMIN', 'PERSIST_RO_VARIABLES_ADMIN',
        'REPLICATION_APPLIER', 'REPLICATION_SLAVE_ADMIN', 'RESOURCE_GROUP_ADMIN', 'RESOURCE_GROUP_USER', 'ROLE_ADMIN', 'SENSITIVE_VARIABLES_OBSERVER',
        'SERVICE_CONNECTION_ADMIN', 'SESSION_VARIABLES_ADMIN', 'SET_ANY_DEFINER', 'SHOW_ROUTINE', 'SYSTEM_USER', 'SYSTEM_VARIABLES_ADMIN',
        'TABLE_ENCRYPTION_ADMIN', 'TELEMETRY_LOG_ADMIN', 'TRANSACTION_GTID_TAG', 'XA_RECOVER_ADMIN',
    ];

    /**
     * @param GrammarRelease $release The release whose privileges are known
     */
    public function __construct(public readonly GrammarRelease $release = GrammarRelease::MySql847)
    {
    }

    /**
     * Answers the static privileges of the release, in the order SHOW GRANTS writes them.
     *
     * @return list<string>
     *
     * @example MySQL 5.7 has no role privileges
     *     in_array('CREATE ROLE', (new \MySqlMemory\Account\Catalog(\SqlSemantics\Contract\GrammarRelease::MySql5744))->statics(), true) // => false
     */
    public function statics(): array
    {
        return $this->legacy() ? array_values(array_diff(self::STATIC, ['CREATE ROLE', 'DROP ROLE'])) : self::STATIC;
    }

    /**
     * Answers the dynamic privileges the release registers, in name order.
     *
     * @return list<string>
     */
    public function dynamics(): array
    {
        if ($this->legacy()) {
            return [];
        }
        if ($this->release === GrammarRelease::MySql8044) {
            $names = array_values(array_diff(self::DYNAMIC, ['ALLOW_NONEXISTENT_DEFINER', 'FLUSH_PRIVILEGES', 'OPTIMIZE_LOCAL_TABLE', 'SET_ANY_DEFINER', 'TRANSACTION_GTID_TAG']));
            $names[] = 'SET_USER_ID';
            sort($names, SORT_STRING);

            return $names;
        }

        return self::DYNAMIC;
    }

    /**
     * Tells whether the release is MySQL 5.6 or 5.7.
     */
    public function legacy(): bool
    {
        return $this->release === GrammarRelease::MySql5651 || $this->release === GrammarRelease::MySql5744;
    }

    /**
     * Tells whether a dynamic privilege is registered, by its name in any case.
     *
     * @example A registered privilege
     *     (new \MySqlMemory\Account\Catalog())->registered('backup_admin') // => true
     */
    public function registered(string $name): bool
    {
        return in_array(strtoupper($name), $this->dynamics(), true);
    }

    /**
     * Answers the privileges of a list in the order SHOW GRANTS writes them.
     *
     * @param array<string, true> $names
     * @return list<string>
     */
    public function ordered(array $names): array
    {
        return array_values(array_filter(self::STATIC, static fn (string $name): bool => isset($names[$name])));
    }
}
