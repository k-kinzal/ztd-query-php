<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation\Join;

/**
 * The operator of a join, by the keywords that write it.
 *
 * JOIN, INNER JOIN and CROSS JOIN are equivalent inner joins in MySQL and
 * are kept as written; STRAIGHT_JOIN is an inner join that reads its left
 * operand first. The optional OUTER after LEFT and RIGHT and the optional
 * INNER after NATURAL do not change the meaning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/join.html.
 *
 * @visibility public
 * @example Reading the keywords of a join operator
 *     \SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator::NaturalLeft->keywords() // => ['NATURAL', 'LEFT', 'JOIN']
 */
enum JoinOperator
{
    case Join;
    case Inner;
    case Cross;
    case StraightJoin;
    case Left;
    case Right;
    case Natural;
    case NaturalLeft;
    case NaturalRight;

    /**
     * Answers the keywords the operator is written with.
     *
     * @return list<string>
     * @example Reading the keywords of STRAIGHT_JOIN
     *     \SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator::StraightJoin->keywords() // => ['STRAIGHT_JOIN']
     */
    public function keywords(): array
    {
        return match ($this) {
            self::Join => ['JOIN'],
            self::Inner => ['INNER', 'JOIN'],
            self::Cross => ['CROSS', 'JOIN'],
            self::StraightJoin => ['STRAIGHT_JOIN'],
            self::Left => ['LEFT', 'JOIN'],
            self::Right => ['RIGHT', 'JOIN'],
            self::Natural => ['NATURAL', 'JOIN'],
            self::NaturalLeft => ['NATURAL', 'LEFT', 'JOIN'],
            self::NaturalRight => ['NATURAL', 'RIGHT', 'JOIN'],
        };
    }

    /**
     * Tells whether the join merges the columns its operands have in common.
     *
     * @example Telling a natural join
     *     \SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator::Natural->natural() // => true
     */
    public function natural(): bool
    {
        return $this === self::Natural || $this === self::NaturalLeft || $this === self::NaturalRight;
    }

    /**
     * Tells whether the join keeps every row of its left operand, extending the right one with NULLs.
     *
     * @example Telling a left join
     *     \SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator::Left->keepsLeft() // => true
     */
    public function keepsLeft(): bool
    {
        return $this === self::Left || $this === self::NaturalLeft;
    }

    /**
     * Tells whether the join keeps every row of its right operand, extending the left one with NULLs.
     *
     * @example Telling a right join
     *     \SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator::NaturalRight->keepsRight() // => true
     */
    public function keepsRight(): bool
    {
        return $this === self::Right || $this === self::NaturalRight;
    }

    /**
     * Tells whether the grammar requires an ON or USING condition after the right operand.
     *
     * @example Telling that a left join needs a condition
     *     \SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator::Left->conditioned() // => true
     */
    public function conditioned(): bool
    {
        return $this === self::Left || $this === self::Right;
    }
}
