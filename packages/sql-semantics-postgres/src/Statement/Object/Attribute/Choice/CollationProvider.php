<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice;

/**
 * The `provider` attribute of a collation: the library that provides it.
 *
 * `DefineCollation` compares the text without regard to case. The builtin
 * provider exists from PostgreSQL 17 on; PostgreSQL 16 reports it as an
 * unrecognized provider.
 * Source: https://www.postgresql.org/docs/17/sql-createcollation.html, `DefineCollation` in `src/backend/commands/collationcmds.c` of PostgreSQL 16 and 17.
 *
 * @visibility public
 * @example Reading a provider
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\CollationProvider::read('ICU')?->name // => 'Icu'
 */
enum CollationProvider: string implements Choice
{
    case Builtin = 'builtin';
    case Icu = 'icu';
    case Libc = 'libc';

    /**
     * Answers the provider the text names without regard to case, or null.
     */
    public static function read(string $text): ?self
    {
        return self::tryFrom(strtolower($text));
    }
}
