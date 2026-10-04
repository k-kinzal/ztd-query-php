<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

/**
 * The functions that are only window functions.
 *
 * Each case holds the keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-descriptions.html.
 *
 * @visibility public
 * @example Reading the argument counts of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Lead->arity() // => [1, 3]
 */
enum WindowFunctionKind: string
{
    case RowNumber = 'ROW_NUMBER';
    case Rank = 'RANK';
    case DenseRank = 'DENSE_RANK';
    case CumulativeDistribution = 'CUME_DIST';
    case PercentRank = 'PERCENT_RANK';
    case Tile = 'NTILE';
    case Lead = 'LEAD';
    case Lag = 'LAG';
    case FirstValue = 'FIRST_VALUE';
    case LastValue = 'LAST_VALUE';
    case NthValue = 'NTH_VALUE';

    /**
     * Answers the smallest and the largest number of arguments.
     *
     * @return array{int, int}
     */
    public function arity(): array
    {
        return match ($this) {
            self::RowNumber, self::Rank, self::DenseRank, self::CumulativeDistribution, self::PercentRank => [0, 0],
            self::Tile, self::FirstValue, self::LastValue => [1, 1],
            self::Lead, self::Lag => [1, 3],
            self::NthValue => [2, 2],
        };
    }

    /**
     * Tells whether the function takes RESPECT NULLS or IGNORE NULLS.
     */
    public function treatsNulls(): bool
    {
        return in_array($this, [self::Lead, self::Lag, self::FirstValue, self::LastValue, self::NthValue], true);
    }

    /**
     * Answers the position of the argument that must be an integer, a parameter or a variable, if any.
     */
    public function counted(): ?int
    {
        return match ($this) {
            self::Tile => 0,
            self::Lead, self::Lag => 1,
            self::RowNumber, self::Rank, self::DenseRank, self::CumulativeDistribution, self::PercentRank, self::FirstValue, self::LastValue, self::NthValue => null,
        };
    }
}
