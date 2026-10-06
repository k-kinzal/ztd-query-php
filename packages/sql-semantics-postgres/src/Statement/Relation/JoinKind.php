<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

/**
 * The kind of a join.
 *
 * Mirrors PostgreSQL's `JoinType` for the joins the grammar writes; CROSS
 * JOIN is an inner join without a condition and is kept as its own kind
 * because it is written so. INNER and OUTER are optional words.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-JOIN.
 *
 * @visibility public
 * @example Telling which sides a join can extend with NULLs
 *     [\SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind::Left->extendsRight(), \SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind::Left->extendsLeft()] // => [true, false]
 */
enum JoinKind: string
{
    case Cross = 'CROSS';
    case Inner = 'INNER';
    case Left = 'LEFT';
    case Right = 'RIGHT';
    case Full = 'FULL';

    /**
     * Tells whether rows of the left side without a match are kept, with NULLs for the right side.
     */
    public function extendsRight(): bool
    {
        return $this === self::Left || $this === self::Full;
    }

    /**
     * Tells whether rows of the right side without a match are kept, with NULLs for the left side.
     */
    public function extendsLeft(): bool
    {
        return $this === self::Right || $this === self::Full;
    }

    /**
     * Answers the keywords written before JOIN; INNER is optional and not written.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return match ($this) {
            self::Inner => [],
            self::Cross, self::Left, self::Right, self::Full => [$this->value],
        };
    }
}
