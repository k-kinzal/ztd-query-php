<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice;

/**
 * The `alignment` attribute of a base type: the storage alignment of its values.
 *
 * `DefineType` compares the text without regard to case, and accepts the
 * names an unquoted type keyword is translated to: `double precision` reads
 * as `pg_catalog.float8`, `integer` as `pg_catalog.int4`, `smallint` as
 * `pg_catalog.int2` and `char` as `pg_catalog.bpchar`.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, `DefineType` in `src/backend/commands/typecmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading an alignment written as a type keyword
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Alignment::read('pg_catalog.int4')?->name // => 'Int'
 */
enum Alignment: string implements Choice
{
    case Char = 'char';
    case Short = 'int2';
    case Int = 'int4';
    case Double = 'double';

    /**
     * The texts the server accepts for each alignment, in lower case.
     */
    private const SPELLINGS = [
        'double' => self::Double, 'float8' => self::Double, 'pg_catalog.float8' => self::Double,
        'int4' => self::Int, 'pg_catalog.int4' => self::Int,
        'int2' => self::Short, 'pg_catalog.int2' => self::Short,
        'char' => self::Char, 'pg_catalog.bpchar' => self::Char,
    ];

    /**
     * Answers the alignment the text names without regard to case, or null.
     */
    public static function read(string $text): ?self
    {
        return self::SPELLINGS[strtolower($text)] ?? null;
    }
}
