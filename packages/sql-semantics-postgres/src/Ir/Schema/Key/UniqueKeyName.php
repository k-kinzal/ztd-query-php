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
        $attributes = implode('_', self::attributeNames($key));
        $pass = 0;
        do {
            $suffix = $pass === 0 ? 'key' : 'key' . $pass;
            $name = IndexNames::candidate($table->value, $attributes, $suffix);
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
        return IndexNames::attributes([...$key->keys, ...$key->included]);
    }
}
