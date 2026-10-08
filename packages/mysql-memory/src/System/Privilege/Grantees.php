<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Catalog;
use MySqlMemory\Account\Privileges;
use MySqlMemory\System\Reading;

/**
 * The accounts and their privileges as the grant tables and the privilege tables of INFORMATION_SCHEMA hold them.
 *
 * The grant tables name each static privilege in a column of its own, holding Y or N; a table
 * or column grant holds a set of privilege names. INFORMATION_SCHEMA names an account as
 * 'user'@'host' and lists the accounts as the server checks them: those of a host without a
 * wildcard first, each kind with the installed accounts by user name and then the others in
 * the order they were created (verified on a live 8.4.7 server). The emulator does not record who granted a
 * privilege and when, so it reports the grants as made by root@localhost when the server started.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/information-schema-user-privileges-table.html.
 *
 * @visibility MySqlMemory
 */
final class Grantees
{
    /**
     * The column of mysql.user, mysql.db and the like that holds each static privilege, by privilege name.
     */
    public const COLUMNS = [
        'SELECT' => 'Select_priv', 'INSERT' => 'Insert_priv', 'UPDATE' => 'Update_priv', 'DELETE' => 'Delete_priv', 'CREATE' => 'Create_priv',
        'DROP' => 'Drop_priv', 'RELOAD' => 'Reload_priv', 'SHUTDOWN' => 'Shutdown_priv', 'PROCESS' => 'Process_priv', 'FILE' => 'File_priv',
        'REFERENCES' => 'References_priv', 'INDEX' => 'Index_priv', 'ALTER' => 'Alter_priv', 'SHOW DATABASES' => 'Show_db_priv', 'SUPER' => 'Super_priv',
        'CREATE TEMPORARY TABLES' => 'Create_tmp_table_priv', 'LOCK TABLES' => 'Lock_tables_priv', 'EXECUTE' => 'Execute_priv',
        'REPLICATION SLAVE' => 'Repl_slave_priv', 'REPLICATION CLIENT' => 'Repl_client_priv', 'CREATE VIEW' => 'Create_view_priv',
        'SHOW VIEW' => 'Show_view_priv', 'CREATE ROUTINE' => 'Create_routine_priv', 'ALTER ROUTINE' => 'Alter_routine_priv',
        'CREATE USER' => 'Create_user_priv', 'EVENT' => 'Event_priv', 'TRIGGER' => 'Trigger_priv', 'CREATE TABLESPACE' => 'Create_tablespace_priv',
        'CREATE ROLE' => 'Create_role_priv', 'DROP ROLE' => 'Drop_role_priv',
    ];

    /**
     * The privileges a table grant set lists, in the order of the set.
     */
    public const TABLE = ['SELECT' => 'Select', 'INSERT' => 'Insert', 'UPDATE' => 'Update', 'DELETE' => 'Delete', 'CREATE' => 'Create', 'DROP' => 'Drop', 'GRANT' => 'Grant', 'REFERENCES' => 'References', 'INDEX' => 'Index', 'ALTER' => 'Alter', 'CREATE VIEW' => 'Create View', 'SHOW VIEW' => 'Show view', 'TRIGGER' => 'Trigger'];

    /**
     * The privileges a column grant set lists, in the order of the set.
     */
    public const COLUMN = ['SELECT' => 'Select', 'INSERT' => 'Insert', 'UPDATE' => 'Update', 'REFERENCES' => 'References'];

    /**
     * The privileges a routine grant set lists, in the order of the set.
     */
    public const ROUTINE = ['EXECUTE' => 'Execute', 'ALTER ROUTINE' => 'Alter Routine', 'GRANT' => 'Grant'];

    /**
     * Answers the accounts as the grant tables order them: by host, then user name.
     *
     * @return list<Account>
     */
    public static function stored(Reading $reading): array
    {
        $accounts = array_values($reading->instance->accounts->accounts);
        usort($accounts, static fn (Account $left, Account $right): int => [$left->identity->host, $left->identity->user] <=> [$right->identity->host, $right->identity->user]);

        return $accounts;
    }

    /**
     * Answers the accounts as the server checks them: those of a host without a wildcard first, each kind with the installed accounts by user name and then the others in the order they were created.
     *
     * @return list<Account>
     */
    public static function checked(Reading $reading): array
    {
        $accounts = array_values($reading->instance->accounts->accounts);
        $installed = ['mysql.infoschema', 'mysql.session', 'mysql.sys', 'root'];
        $rank = static fn (Account $account, int $index): array => [strpbrk($account->identity->host, '%_') !== false, !in_array($account->identity->user, $installed, true), in_array($account->identity->user, $installed, true) ? $account->identity->user : '', $index];
        $ranked = array_map(static fn (Account $account, int $index): array => [$rank($account, $index), $account], $accounts, array_keys($accounts));
        usort($ranked, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return array_map(static fn (array $entry): Account => $entry[1], $ranked);
    }

    /**
     * Answers an account as INFORMATION_SCHEMA names it: 'user'@'host'.
     */
    public static function grantee(Account $account): string
    {
        return "'" . $account->identity->user . "'@'" . $account->identity->host . "'";
    }

    /**
     * Answers the Y or N columns of the static privileges a level holds, for the privileges the level can hold.
     *
     * @param list<string> $names The privileges the level can hold
     *
     * @return array<string, string>
     */
    public static function flags(Privileges $privileges, array $names): array
    {
        $flags = [];
        foreach ($names as $name) {
            $flags[self::COLUMNS[$name]] = isset($privileges->names[$name]) ? 'Y' : 'N';
        }
        $flags['Grant_priv'] = $privileges->grantOption ? 'Y' : 'N';

        return $flags;
    }

    /**
     * Answers a set of privilege names, as a grant table holds it.
     *
     * @param array<string, true> $held The privileges held, by name
     * @param array<string, string> $members The privileges of the set and the names it writes them as, in its order
     */
    public static function set(array $held, array $members, bool $grant = false): string
    {
        $listed = [];
        foreach ($members as $name => $written) {
            if (isset($held[$name]) || ($name === 'GRANT' && $grant)) {
                $listed[] = $written;
            }
        }

        return implode(',', $listed);
    }

    /**
     * Answers the time the grants are reported to have been made: when the server started.
     */
    public static function time(Reading $reading): string
    {
        return date('Y-m-d H:i:s', (int) floor($reading->instance->started));
    }

    /**
     * Answers the static privileges of the global level, in the order the server lists them.
     *
     * @return list<string>
     */
    public static function global(): array
    {
        return Catalog::STATIC;
    }
}
