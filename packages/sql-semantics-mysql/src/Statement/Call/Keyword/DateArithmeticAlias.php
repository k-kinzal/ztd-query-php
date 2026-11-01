<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Keyword;

/**
 * Alternative names of interval date arithmetic, with their operation fixed.
 *
 * @visibility public
 * @example Reading the operation of an interval synonym
 *     \SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias::SubDate->subtracts() // => true
 */
enum DateArithmeticAlias: string
{
    case AddDate = 'ADDDATE';
    case SubDate = 'SUBDATE';

    /**
     * Answers whether this function subtracts its interval.
     */
    public function subtracts(): bool
    {
        return $this === self::SubDate;
    }
}
