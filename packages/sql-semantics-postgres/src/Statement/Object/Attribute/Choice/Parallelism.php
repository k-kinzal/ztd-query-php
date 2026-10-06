<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice;

/**
 * The `parallel` attribute of an aggregate: whether it is safe to run in parallel mode.
 *
 * `DefineAggregate` compares the text with `safe`, `restricted` and
 * `unsafe` exactly, so a quoted `'SAFE'` is rejected while an unquoted `SAFE`
 * is folded to lower case and accepted.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, `DefineAggregate` in `src/backend/commands/aggregatecmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading a parallel mode
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Parallelism::read('SAFE') // => null
 */
enum Parallelism: string implements Choice
{
    case Safe = 'safe';
    case Restricted = 'restricted';
    case Unsafe = 'unsafe';

    /**
     * Answers the mode the text names exactly, or null.
     */
    public static function read(string $text): ?self
    {
        return self::tryFrom($text);
    }
}
