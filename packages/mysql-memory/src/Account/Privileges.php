<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

/**
 * The static privileges an account holds at one level: the global level, a database, a table or a routine.
 *
 * A table also holds the privileges granted on its columns. GRANT OPTION is held for the whole
 * level, not for one privilege. Privileges are named as GRANT names them, in upper case.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/privileges-provided.html.
 *
 * @visibility MySqlMemory
 */
final class Privileges
{
    /**
     * @param array<string, true> $names The privileges held, by name
     * @param bool $grantOption Whether GRANT OPTION is held
     * @param array<int|string, array<string, true>> $columns The privileges held on each column, by column name
     */
    public function __construct(public array $names = [], public bool $grantOption = false, public array $columns = [])
    {
    }

    /**
     * Grants privileges.
     *
     * @param list<string> $names
     */
    public function add(array $names): void
    {
        foreach ($names as $name) {
            $this->names[$name] = true;
        }
    }

    /**
     * Grants a privilege on columns.
     *
     * @param list<string> $columns
     */
    public function addColumns(string $name, array $columns): void
    {
        foreach ($columns as $column) {
            $this->columns[$column][$name] = true;
        }
    }

    /**
     * Revokes privileges, and the same privileges on every column.
     *
     * @param list<string> $names
     */
    public function remove(array $names): void
    {
        foreach ($names as $name) {
            unset($this->names[$name]);
            foreach ($this->columns as $column => $held) {
                unset($held[$name]);
                $this->columns[$column] = $held;
            }
        }
        $this->columns = array_filter($this->columns, static fn (array $held): bool => $held !== []);
    }

    /**
     * Revokes a privilege on columns.
     *
     * @param list<string> $columns
     */
    public function removeColumns(string $name, array $columns): void
    {
        foreach ($columns as $column) {
            unset($this->columns[$column][$name]);
        }
        $this->columns = array_filter($this->columns, static fn (array $held): bool => $held !== []);
    }

    /**
     * Tells whether nothing is held, not even GRANT OPTION.
     */
    public function empty(): bool
    {
        return $this->names === [] && $this->columns === [] && !$this->grantOption;
    }

    /**
     * Answers the columns a privilege is held on, in name order.
     *
     * @return list<string>
     */
    public function columnsOf(string $name): array
    {
        $columns = [];
        foreach ($this->columns as $column => $held) {
            if (isset($held[$name])) {
                $columns[] = (string) $column;
            }
        }
        sort($columns, SORT_STRING);

        return $columns;
    }

    /**
     * Adds what another level holds to this one.
     */
    public function merge(self $other): void
    {
        $this->names += $other->names;
        $this->grantOption = $this->grantOption || $other->grantOption;
        foreach ($other->columns as $column => $held) {
            $this->columns[$column] = ($this->columns[$column] ?? []) + $held;
        }
    }
}
