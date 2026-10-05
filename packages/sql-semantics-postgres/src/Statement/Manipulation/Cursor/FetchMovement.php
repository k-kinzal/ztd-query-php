<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor;

use SqlSemantics\Rendering\Output;

/**
 * Where FETCH or MOVE positions the cursor, as written.
 *
 * Mirrors the `direction` and `howMany` of PostgreSQL's `FetchStmt`: NEXT
 * and a missing direction are FORWARD 1, PRIOR is BACKWARD 1, FIRST is
 * ABSOLUTE 1, LAST is ABSOLUTE -1, a count alone is FORWARD count and ALL is
 * FORWARD ALL. The spellings are kept because they are different requests
 * of the grammar. The counted movements take a count.
 * Source: https://www.postgresql.org/docs/17/sql-fetch.html.
 *
 * @visibility public
 * @example Reading a movement
 *     $fetch = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('FETCH BACKWARD 5 FROM c');
 *     [$fetch->statement->movement, $fetch->statement->count->magnitude->digits] // => [\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\FetchMovement::BackwardCount, '5']
 */
enum FetchMovement
{
    case Implicit;
    case Next;
    case Prior;
    case First;
    case Last;
    case Absolute;
    case Relative;
    case Count;
    case All;
    case Forward;
    case ForwardCount;
    case ForwardAll;
    case Backward;
    case BackwardCount;
    case BackwardAll;

    /**
     * Tells whether the movement takes a count.
     */
    public function counted(): bool
    {
        return in_array($this, [self::Absolute, self::Relative, self::Count, self::ForwardCount, self::BackwardCount], true);
    }

    /**
     * Writes the keywords before the count.
     */
    public function write(Output $out): void
    {
        $keywords = match ($this) {
            self::Implicit, self::Count => [],
            self::Next => ['NEXT'],
            self::Prior => ['PRIOR'],
            self::First => ['FIRST'],
            self::Last => ['LAST'],
            self::Absolute => ['ABSOLUTE'],
            self::Relative => ['RELATIVE'],
            self::All => ['ALL'],
            self::Forward, self::ForwardCount => ['FORWARD'],
            self::ForwardAll => ['FORWARD', 'ALL'],
            self::Backward, self::BackwardCount => ['BACKWARD'],
            self::BackwardAll => ['BACKWARD', 'ALL'],
        };
        if ($keywords !== []) {
            $out->keyword(...$keywords);
        }
    }
}
