<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind;

/**
 * Writes the GRANT statements SHOW GRANTS answers for an account.
 *
 * The global static privileges come first, `USAGE` when there is none, then the dynamic
 * privileges, those without GRANT OPTION before those with it, each list in name order. The
 * databases follow, the names without a wildcard (`%` or `_`) before those with one, each group
 * in byte order, then the tables in order of database and name, then the procedures and the
 * functions, which never write ALL PRIVILEGES, then the PROXY grants and last the roles, those
 * without ADMIN OPTION before those with it. A database that holds every database privilege,
 * and a table that holds every table privilege, writes ALL PRIVILEGES; a column privilege
 * follows the table privilege of the same name, with its columns in name order (verified on a
 * live 8.4 server).
 *
 * MySQL 5.6 and 5.7 quote the account as strings, write the column names of a column privilege
 * unquoted, write ALL PRIVILEGES for every static privilege at the global level as well, and
 * list the databases without a wildcard before those with one, each group in the order granted.
 * MySQL 5.6 writes on the global line the password string of a mysql_native_password account
 * (IDENTIFIED BY PASSWORD), the TLS requirement and, after WITH GRANT OPTION, the resource limits
 * that are set (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-grants.html,
 * https://dev.mysql.com/doc/refman/5.6/en/show-grants.html.
 *
 * @visibility MySqlMemory
 */
final class GrantText
{
    /**
     * Writes the statements of the privileges and roles of an account.
     *
     * @param Identity $identity The account the statements name
     * @param Grants $grants The privileges, those of the roles in use included
     * @param array<string, array{Identity, bool}> $roles The roles granted, each telling whether ADMIN OPTION is held
     * @param GrammarRelease $release The release whose form the statements take
     * @param Account|null $account The account whose authentication, TLS requirement and limits MySQL 5.6 writes
     * @return list<string>
     */
    public function lines(Identity $identity, Grants $grants, array $roles, GrammarRelease $release = GrammarRelease::MySql847, ?Account $account = null): array
    {
        $legacy = (new Catalog($release))->legacy();
        $to = ' TO ' . ($legacy ? $identity->quoted() : $identity->backquoted());

        return [
            ...$this->globalLines($grants, $to, $release, $account),
            ...$this->objectLines($grants, $to, $legacy),
            ...$this->roleLines($grants, $roles, $to, $legacy),
        ];
    }

    /**
     * Writes the global statements: the static privileges, `USAGE` when there is none, then the
     * dynamic privileges, those without GRANT OPTION before those with it, each in name order.
     *
     * @param Grants $grants The privileges of the account
     * @param string $to The TO clause naming the account
     * @param GrammarRelease $release The release whose form the statements take
     * @param Account|null $account The account whose authentication, TLS requirement and limits MySQL 5.6 writes
     * @return list<string>
     */
    public function globalLines(Grants $grants, string $to, GrammarRelease $release, ?Account $account): array
    {
        $catalog = new Catalog($release);
        $global = $catalog->ordered($grants->global->names);
        $global = $catalog->legacy() && array_diff($catalog->statics(), $global) === [] ? ['ALL PRIVILEGES'] : $global;
        $grantable = $grants->global->grantOption ? ' WITH GRANT OPTION' : '';
        $lines = [$release === GrammarRelease::MySql5651 && $account !== null
            ? 'GRANT ' . ($global === [] ? 'USAGE' : implode(', ', $global)) . ' ON *.*' . $to . $this->account($account, $grantable)
            : 'GRANT ' . ($global === [] ? 'USAGE' : implode(', ', $global)) . ' ON *.*' . $to . $grantable];
        foreach ([false, true] as $option) {
            $names = array_keys(array_filter($grants->dynamic, static fn (bool $held): bool => $held === $option));
            sort($names, SORT_STRING);
            if ($names !== []) {
                $lines[] = 'GRANT ' . implode(',', $names) . ' ON *.*' . $to . ($option ? ' WITH GRANT OPTION' : '');
            }
        }

        return $lines;
    }

    /**
     * Writes the statements of the databases, the tables and the stored routines.
     *
     * The databases without a wildcard come before those with one, each group in byte order (in
     * the order granted on MySQL 5.6 and 5.7), then the tables in order of database and name,
     * then the procedures and the functions, which never write ALL PRIVILEGES.
     *
     * @param Grants $grants The privileges of the account
     * @param string $to The TO clause naming the account
     * @param bool $legacy Whether the statements take the form of MySQL 5.6 and 5.7
     * @return list<string>
     */
    public function objectLines(Grants $grants, string $to, bool $legacy): array
    {
        $lines = [];
        $databases = array_map('strval', array_keys($grants->databases));
        usort($databases, static fn (string $a, string $b): int => $legacy ? (strpbrk($a, '%_') !== false) <=> (strpbrk($b, '%_') !== false) : [strpbrk($a, '%_') !== false, $a] <=> [strpbrk($b, '%_') !== false, $b]);
        foreach ($databases as $database) {
            $privileges = $grants->databases[$database];
            $lines[] = 'GRANT ' . $this->privileges($privileges, Catalog::DATABASE, $legacy) . ' ON ' . $this->quote($database) . '.*' . $to . ($privileges->grantOption ? ' WITH GRANT OPTION' : '');
        }
        $tables = array_values($grants->tables);
        usort($tables, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        foreach ($tables as [$database, $table, $privileges]) {
            $lines[] = 'GRANT ' . $this->privileges($privileges, Catalog::TABLE, $legacy) . ' ON ' . $this->quote($database) . '.' . $this->quote($table) . $to . ($privileges->grantOption ? ' WITH GRANT OPTION' : '');
        }
        $routines = array_values($grants->routines);
        usort($routines, static fn (array $a, array $b): int => [$a[0] === 'FUNCTION', $a[1], $a[2]] <=> [$b[0] === 'FUNCTION', $b[1], $b[2]]);
        foreach ($routines as [$kind, $database, $routine, $privileges]) {
            $names = (new Catalog())->ordered($privileges->names);
            $lines[] = 'GRANT ' . ($names === [] ? 'USAGE' : implode(', ', $names)) . ' ON ' . $kind . ' ' . $this->quote($database) . '.' . $this->quote($routine) . $to . ($privileges->grantOption ? ' WITH GRANT OPTION' : '');
        }

        return $lines;
    }

    /**
     * Writes the PROXY statements, then the roles, those without ADMIN OPTION before those with it,
     * each in name order.
     *
     * @param Grants $grants The privileges of the account
     * @param array<string, array{Identity, bool}> $roles The roles granted, each telling whether ADMIN OPTION is held
     * @param string $to The TO clause naming the account
     * @param bool $legacy Whether the proxied account is quoted as strings, as MySQL 5.6 and 5.7 quote it
     * @return list<string>
     */
    public function roleLines(Grants $grants, array $roles, string $to, bool $legacy): array
    {
        $lines = [];
        foreach ($grants->proxies as [$proxied, $option]) {
            $lines[] = 'GRANT PROXY ON ' . ($legacy ? $proxied->quoted() : $proxied->backquoted()) . $to . ($option ? ' WITH GRANT OPTION' : '');
        }
        foreach ([false, true] as $admin) {
            $names = array_map(static fn (array $role): string => $role[0]->backquoted(), array_values(array_filter($roles, static fn (array $role): bool => $role[1] === $admin)));
            sort($names, SORT_STRING);
            if ($names !== []) {
                $lines[] = 'GRANT ' . implode(',', $names) . $to . ($admin ? ' WITH ADMIN OPTION' : '');
            }
        }

        return $lines;
    }

    /**
     * Writes what MySQL 5.6 writes after the account on the global line: the password string, the TLS requirement, then WITH and GRANT OPTION and the resource limits set.
     *
     * @param string $option The GRANT OPTION clause, or an empty string
     */
    public function account(Account $account, string $option): string
    {
        $text = '';
        if ($account->hash !== '' && in_array($account->plugin, ['mysql_native_password', 'mysql_old_password'], true)) {
            $text .= " IDENTIFIED BY PASSWORD '" . Identity::escape($account->hash) . "'";
        }
        $text .= match ($account->tls) {
            TlsKind::None => '',
            TlsKind::Ssl => ' REQUIRE SSL',
            TlsKind::X509 => ' REQUIRE X509',
            TlsKind::Specified => ' REQUIRE' . implode('', array_map(static fn (string $name): string => ' ' . $name . " '" . Identity::escape($account->tlsConditions[$name]) . "'", array_values(array_filter(['ISSUER', 'SUBJECT', 'CIPHER'], static fn (string $name): bool => isset($account->tlsConditions[$name]))))),
        };
        $limits = '';
        foreach (['MAX_QUERIES_PER_HOUR', 'MAX_UPDATES_PER_HOUR', 'MAX_CONNECTIONS_PER_HOUR', 'MAX_USER_CONNECTIONS'] as $name) {
            if (($account->limits[$name] ?? 0) !== 0) {
                $limits .= ' ' . $name . ' ' . $account->limits[$name];
            }
        }
        if ($limits === '') {
            return $text . $option;
        }

        return $text . ($option === '' ? ' WITH' : $option) . $limits;
    }

    /**
     * Writes the privileges of a database or a table.
     *
     * @param list<string> $all Every privilege the level takes, in order
     * @param bool $legacy Whether the column names are written unquoted, as MySQL 5.6 and 5.7 write them
     */
    public function privileges(Privileges $privileges, array $all, bool $legacy = false): string
    {
        if (array_diff($all, array_keys($privileges->names)) === []) {
            return 'ALL PRIVILEGES';
        }
        $written = [];
        foreach (Catalog::STATIC as $name) {
            if (isset($privileges->names[$name])) {
                $written[] = $name;
            }
            $columns = $privileges->columnsOf($name);
            if ($columns !== []) {
                $written[] = $name . ' (' . implode(', ', $legacy ? $columns : array_map($this->quote(...), $columns)) . ')';
            }
        }

        return $written === [] ? 'USAGE' : implode(', ', $written);
    }

    /**
     * Quotes a name as an identifier.
     */
    public function quote(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
