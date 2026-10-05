<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice;

/**
 * The `finalfunc_modify` and `mfinalfunc_modify` attributes of an aggregate: whether the final function changes the state.
 *
 * `extractModify` compares the text with `read_only`, `shareable` and
 * `read_write` exactly.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, `extractModify` in `src/backend/commands/aggregatecmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading a modification mode
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\FinalModification::read('read_write')?->name // => 'ReadWrite'
 */
enum FinalModification: string implements Choice
{
    case ReadOnly = 'read_only';
    case Shareable = 'shareable';
    case ReadWrite = 'read_write';

    /**
     * Answers the mode the text names exactly, or null.
     */
    public static function read(string $text): ?self
    {
        return self::tryFrom($text);
    }
}
