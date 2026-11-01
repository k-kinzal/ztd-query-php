<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\TableDefinition;

/**
 * The keys of a table in the order the server keeps them, and the role each column plays in them.
 *
 * The server keeps the primary key first, then the unique keys whose columns are all NOT NULL,
 * then the other unique keys, then the ordinary and spatial keys, and the full-text keys last,
 * each group in the order the keys were declared. A table without a primary key uses its first
 * unique key of NOT NULL columns as one. SHOW COLUMNS reports a column of the primary key as
 * PRI, the column of a unique key of one column as UNI, and the first column of any other key
 * as MUL (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-columns.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility MySqlMemory
 */
final class Keys
{
    /**
     * Answers the keys of a table in the order the server keeps them.
     *
     * @return list<Key>
     */
    public function ordered(TableDefinition $table): array
    {
        $ranked = [];
        foreach ($table->keys as $position => $key) {
            $ranked[] = [$this->rank($key, $table), $position, $key];
        }
        usort($ranked, static fn (array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]]);

        return array_map(static fn (array $entry): Key => $entry[2], $ranked);
    }

    /**
     * Answers the group of a key in the order the server keeps the keys.
     */
    public function rank(Key $key, TableDefinition $table): int
    {
        return match ($key->kind) {
            KeyKind::Primary => 0,
            KeyKind::Unique => $this->notNull($key, $table) ? 1 : 2,
            KeyKind::Index, KeyKind::Spatial => 3,
            KeyKind::FullText => 4,
        };
    }

    /**
     * Tells whether every column of a key is NOT NULL.
     */
    public function notNull(Key $key, TableDefinition $table): bool
    {
        foreach ($key->columns as $position) {
            if ($table->columns[$position]->nullable()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Answers the key the table uses as its primary key: the primary key, else its first unique key of NOT NULL columns, else null.
     */
    public function primary(TableDefinition $table): ?Key
    {
        foreach ($this->ordered($table) as $key) {
            if ($key->kind === KeyKind::Primary || ($key->kind === KeyKind::Unique && $this->notNull($key, $table))) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Answers the role of a column in the keys, as SHOW COLUMNS reports it: PRI, UNI, MUL or the empty string.
     */
    public function role(TableDefinition $table, int $position): string
    {
        $primary = $this->primary($table);
        if ($primary !== null && in_array($position, $primary->columns, true)) {
            return 'PRI';
        }
        $role = '';
        foreach ($this->ordered($table) as $key) {
            if ($key->columns[0] !== $position || $key === $primary) {
                continue;
            }
            if ($key->kind === KeyKind::Unique && count($key->columns) === 1) {
                return 'UNI';
            }
            $role = 'MUL';
        }

        return $role;
    }
}
