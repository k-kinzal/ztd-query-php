<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

/**
 * The privileges granted to an account at every level, and the accounts it may proxy.
 *
 * The global static privileges and GRANT OPTION, the dynamic privileges each with or without
 * GRANT OPTION, the privileges on each database, on each table and its columns, on each stored
 * routine, and the PROXY grants. Routine names are compared in lower case. A level that
 * holds nothing, not even GRANT OPTION, is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html.
 *
 * @visibility MySqlMemory
 */
final class Grants
{
    /**
     * @param Privileges $global The global static privileges
     * @param array<string, bool> $dynamic The dynamic privileges, by upper-case name, each telling whether GRANT OPTION is held for it
     * @param array<string, Privileges> $databases The privileges on each database, by name
     * @param array<string, array{string, string, Privileges}> $tables The database, the name and the privileges of each table, by database and name
     * @param array<string, array{Identity, bool}> $proxies The accounts the account may proxy, each telling whether GRANT OPTION is held, by key
     * @param array<string, array{string, string, string, Privileges}> $routines The kind (PROCEDURE or FUNCTION), the database, the lower-case name and the privileges of each routine, by kind, database and name
     */
    public function __construct(
        public Privileges $global = new Privileges(),
        public array $dynamic = [],
        public array $databases = [],
        public array $tables = [],
        public array $proxies = [],
        public array $routines = [],
    ) {
    }

    /**
     * Answers the privileges of a database, kept from now on.
     */
    public function database(string $name): Privileges
    {
        return $this->databases[$name] ??= new Privileges();
    }

    /**
     * Answers the privileges of a table, kept from now on.
     */
    public function table(string $database, string $name): Privileges
    {
        $key = $database . "\0" . $name;
        $this->tables[$key] ??= [$database, $name, new Privileges()];

        return $this->tables[$key][2];
    }

    /**
     * Answers the privileges of a table if any are kept.
     */
    public function findTable(string $database, string $name): ?Privileges
    {
        return ($this->tables[$database . "\0" . $name] ?? null)[2] ?? null;
    }

    /**
     * Answers the privileges of a routine, kept from now on.
     *
     * @param string $kind PROCEDURE or FUNCTION
     */
    public function routine(string $kind, string $database, string $name): Privileges
    {
        $key = $kind . "\0" . $database . "\0" . mb_strtolower($name, 'UTF-8');
        $this->routines[$key] ??= [$kind, $database, mb_strtolower($name, 'UTF-8'), new Privileges()];

        return $this->routines[$key][3];
    }

    /**
     * Answers the privileges of a routine if any are kept.
     *
     * @param string $kind PROCEDURE or FUNCTION
     */
    public function findRoutine(string $kind, string $database, string $name): ?Privileges
    {
        return ($this->routines[$kind . "\0" . $database . "\0" . mb_strtolower($name, 'UTF-8')] ?? null)[3] ?? null;
    }

    /**
     * Drops the levels that hold nothing.
     */
    public function prune(): void
    {
        $this->databases = array_filter($this->databases, static fn (Privileges $privileges): bool => !$privileges->empty());
        $this->tables = array_filter($this->tables, static fn (array $table): bool => !$table[2]->empty());
        $this->routines = array_filter($this->routines, static fn (array $routine): bool => !$routine[3]->empty());
    }

    /**
     * Revokes every privilege at every level, GRANT OPTION and the PROXY grants included.
     */
    public function clear(): void
    {
        $this->global = new Privileges();
        $this->dynamic = [];
        $this->databases = [];
        $this->tables = [];
        $this->proxies = [];
        $this->routines = [];
    }

    /**
     * Answers a copy that changes apart from this one.
     */
    public function copy(): self
    {
        $copy = new self(clone $this->global, $this->dynamic, [], [], $this->proxies);
        foreach ($this->databases as $name => $privileges) {
            $copy->databases[$name] = clone $privileges;
        }
        foreach ($this->tables as $key => [$database, $name, $privileges]) {
            $copy->tables[$key] = [$database, $name, clone $privileges];
        }
        foreach ($this->routines as $key => [$kind, $database, $name, $privileges]) {
            $copy->routines[$key] = [$kind, $database, $name, clone $privileges];
        }

        return $copy;
    }

    /**
     * Adds what other grants hold to these, as the privileges of a role add to those of its grantee.
     */
    public function merge(self $other): void
    {
        $this->global->merge($other->global);
        foreach ($other->dynamic as $name => $option) {
            $this->dynamic[$name] = ($this->dynamic[$name] ?? false) || $option;
        }
        foreach ($other->databases as $name => $privileges) {
            $this->database($name)->merge($privileges);
        }
        foreach ($other->tables as [$database, $name, $privileges]) {
            $this->table($database, $name)->merge($privileges);
        }
        foreach ($other->routines as [$kind, $database, $name, $privileges]) {
            $this->routine($kind, $database, $name)->merge($privileges);
        }
        $this->proxies += $other->proxies;
    }
}
