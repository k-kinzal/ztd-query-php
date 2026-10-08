<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

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
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-grants.html.
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
     * @return list<string>
     */
    public function lines(Identity $identity, Grants $grants, array $roles): array
    {
        $to = ' TO ' . $identity->backquoted();
        $catalog = new Catalog();
        $global = $catalog->ordered($grants->global->names);
        $lines = ['GRANT ' . ($global === [] ? 'USAGE' : implode(', ', $global)) . ' ON *.*' . $to . ($grants->global->grantOption ? ' WITH GRANT OPTION' : '')];
        foreach ([false, true] as $option) {
            $names = array_keys(array_filter($grants->dynamic, static fn (bool $held): bool => $held === $option));
            sort($names, SORT_STRING);
            if ($names !== []) {
                $lines[] = 'GRANT ' . implode(',', $names) . ' ON *.*' . $to . ($option ? ' WITH GRANT OPTION' : '');
            }
        }
        $databases = array_map('strval', array_keys($grants->databases));
        usort($databases, static fn (string $a, string $b): int => [strpbrk($a, '%_') !== false, $a] <=> [strpbrk($b, '%_') !== false, $b]);
        foreach ($databases as $database) {
            $privileges = $grants->databases[$database];
            $lines[] = 'GRANT ' . $this->privileges($privileges, Catalog::DATABASE) . ' ON ' . $this->quote($database) . '.*' . $to . ($privileges->grantOption ? ' WITH GRANT OPTION' : '');
        }
        $tables = array_values($grants->tables);
        usort($tables, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        foreach ($tables as [$database, $table, $privileges]) {
            $lines[] = 'GRANT ' . $this->privileges($privileges, Catalog::TABLE) . ' ON ' . $this->quote($database) . '.' . $this->quote($table) . $to . ($privileges->grantOption ? ' WITH GRANT OPTION' : '');
        }
        $routines = array_values($grants->routines);
        usort($routines, static fn (array $a, array $b): int => [$a[0] === 'FUNCTION', $a[1], $a[2]] <=> [$b[0] === 'FUNCTION', $b[1], $b[2]]);
        foreach ($routines as [$kind, $database, $routine, $privileges]) {
            $names = (new Catalog())->ordered($privileges->names);
            $lines[] = 'GRANT ' . ($names === [] ? 'USAGE' : implode(', ', $names)) . ' ON ' . $kind . ' ' . $this->quote($database) . '.' . $this->quote($routine) . $to . ($privileges->grantOption ? ' WITH GRANT OPTION' : '');
        }
        foreach ($grants->proxies as [$proxied, $option]) {
            $lines[] = 'GRANT PROXY ON ' . $proxied->backquoted() . $to . ($option ? ' WITH GRANT OPTION' : '');
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
     * Writes the privileges of a database or a table.
     *
     * @param list<string> $all Every privilege the level takes, in order
     */
    public function privileges(Privileges $privileges, array $all): string
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
                $written[] = $name . ' (' . implode(', ', array_map($this->quote(...), $columns)) . ')';
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
