<?php

declare(strict_types=1);

namespace MySqlMemory\System\Schema;

use MySqlMemory\Command\Show\Keys;
use MySqlMemory\Dictionary\Check;
use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\ForeignKey;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\TableDefinition;

/**
 * The constraints of a table as INFORMATION_SCHEMA lists them: its primary and unique keys, its foreign keys and its CHECK constraints.
 *
 * Each kind is listed by name without regard to case, as the data dictionary keeps them; 5.6
 * and 5.7 list the keys in the order the server keeps them, the primary key first (verified on
 * live 5.7.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-table-constraints-table.html.
 *
 * @visibility MySqlMemory
 */
final class Constraints
{
    /**
     * Answers the primary and unique keys of a table, by name or in the order the server keeps them.
     *
     * @param bool $named Whether to answer them by name, as MySQL 8.0 and later list them, rather than as 5.6 and 5.7 do
     * @return list<Key>
     */
    public static function keys(TableDefinition $table, bool $named = true): array
    {
        $keys = array_values(array_filter((new Keys())->ordered($table), static fn (Key $key): bool => $key->unique()));
        if ($named) {
            usort($keys, static fn (Key $left, Key $right): int => strcasecmp($left->name, $right->name));
        }

        return $keys;
    }

    /**
     * Answers the foreign keys of a table, by name.
     *
     * @return list<ForeignKey>
     */
    public static function foreign(TableDefinition $table): array
    {
        $keys = $table->foreignKeys;
        usort($keys, static fn (ForeignKey $left, ForeignKey $right): int => strcasecmp($left->name, $right->name));

        return $keys;
    }

    /**
     * Answers the CHECK constraints of a table, by name.
     *
     * @return list<Check>
     */
    public static function checks(TableDefinition $table): array
    {
        $checks = $table->checks;
        usort($checks, static fn (Check $left, Check $right): int => strcasecmp($left->name, $right->name));

        return $checks;
    }

    /**
     * Answers the name of the primary or unique key of the parent table a foreign key references, or null when no such key covers its columns.
     */
    public static function referenced(ForeignKey $foreign, Dictionary $dictionary): ?string
    {
        $parent = $dictionary->table($foreign->parentSchema, $foreign->parentTable)?->definition;
        if ($parent === null) {
            return null;
        }
        $wanted = array_map('strtolower', $foreign->parentColumns);
        foreach ((new Keys())->ordered($parent) as $key) {
            $names = array_map(static fn (int $position): string => strtolower($parent->columns[$position]->name), $key->columns);
            if ($key->unique() && $names === $wanted) {
                return $key->name;
            }
        }
        foreach ((new Keys())->ordered($parent) as $key) {
            $names = array_map(static fn (int $position): string => strtolower($parent->columns[$position]->name), $key->columns);
            if (array_slice($names, 0, count($wanted)) === $wanted) {
                return $key->name;
            }
        }

        return null;
    }
}
