<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

/**
 * A pattern-matching keyword used as the operator of a quantified comparison: `a LIKE ANY (…)`.
 *
 * The grammar reads each keyword as the operator it stands for.
 * Source: https://www.postgresql.org/docs/17/functions-matching.html#FUNCTIONS-LIKE,
 * https://www.postgresql.org/docs/17/functions-comparisons.html.
 *
 * @visibility public
 * @example Reading the operator a keyword stands for
 *     \SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\MatchKeyword::NotILike->operator() // => '!~~*'
 */
enum MatchKeyword: string
{
    /**
     * `LIKE`.
     */
    case Like = 'LIKE';

    /**
     * `NOT LIKE`.
     */
    case NotLike = 'NOT LIKE';

    /**
     * `ILIKE`.
     */
    case ILike = 'ILIKE';

    /**
     * `NOT ILIKE`.
     */
    case NotILike = 'NOT ILIKE';

    /**
     * Answers the operator the keyword stands for.
     */
    public function operator(): string
    {
        return match ($this) {
            self::Like => '~~',
            self::NotLike => '!~~',
            self::ILike => '~~*',
            self::NotILike => '!~~*',
        };
    }
}
