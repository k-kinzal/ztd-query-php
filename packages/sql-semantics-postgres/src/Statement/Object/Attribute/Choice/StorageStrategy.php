<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice;

/**
 * The `storage` attribute of a base type: how values of a variable-length type are stored.
 *
 * `DefineType` compares the text without regard to case.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, `DefineType` in `src/backend/commands/typecmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading a storage strategy
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\StorageStrategy::read('EXTENDED')?->name // => 'Extended'
 */
enum StorageStrategy: string implements Choice
{
    case Plain = 'plain';
    case External = 'external';
    case Extended = 'extended';
    case Main = 'main';

    /**
     * Answers the strategy the text names without regard to case, or null.
     */
    public static function read(string $text): ?self
    {
        return self::tryFrom(strtolower($text));
    }
}
