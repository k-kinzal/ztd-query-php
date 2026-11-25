<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Key;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;

/**
 * Resolves a native automatic unique-index name inside a closed new schema.
 *
 * @visibility SqlSemantics
 */
final class UniqueKeyName
{
    /**
     * Allocates the first unused native name in index creation order.
     * @param array<array-key, CreateTable|TableName|true> $occupied
     */
    public static function allocate(Identifier $table, UniqueKey $key, array $occupied): string
    {
        $attributes = self::attributeNames($key);
        $columnPart = implode('_', $attributes);
        $pass = 0;
        do {
            $suffix = $pass === 0 ? 'key' : 'key' . $pass;
            $first = strlen($table->value);
            $second = strlen($columnPart);
            $limit = 61 - strlen($suffix);
            while ($first + $second > $limit) {
                if ($first > $second) {
                    --$first;
                } else {
                    --$second;
                }
            }
            $name = self::clip($table->value, $first) . '_' . self::clip($columnPart, $second) . '_' . $suffix;
            ++$pass;
        } while (isset($occupied[$name]));

        return $name;
    }

    /**
     * Derives PostgreSQL's physical index attribute labels, including generated suffixes.
     * @return non-empty-list<string>
     */
    public static function attributeNames(UniqueKey $key): array
    {
        $seen = [];
        $labels = [];
        foreach ([...$key->keys, ...$key->included] as $field) {
            $original = $field->value;
            $candidate = $original;
            $pass = 1;
            while (isset($seen[$candidate])) {
                $suffix = (string) $pass;
                $candidate = self::clip($original, 63 - strlen($suffix)) . $suffix;
                ++$pass;
            }
            $seen[$candidate] = true;
            $labels[] = $candidate;
        }

        return $labels;
    }

    /**
     * Keeps the largest UTF-8 prefix within an already checked byte budget.
     */
    public static function clip(string $value, int $limit): string
    {
        $length = min(max($limit, 0), strlen($value));
        while ($length < strlen($value) && (ord($value[$length]) & 0xc0) === 0x80) {
            --$length;
        }

        return substr($value, 0, $length);
    }
}
