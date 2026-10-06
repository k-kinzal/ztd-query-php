<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Statement\Scalar;

/**
 * A call of a set function, an aggregate such as COUNT, SUM or GROUP_CONCAT, written with or without a window.
 *
 * Without OVER it aggregates the rows of its query block, so the block
 * returns one row per group, or one row without GROUP BY; with OVER it is a
 * window function and aggregates nothing.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html.
 *
 * @visibility public
 * @example Telling an aggregate from its windowed form
 *     $count = new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count, []);
 *     $windowed = new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count, [], over: new \SqlSemantics\Statement\Identifier\Name('w'));
 *     [$count->aggregates(), $windowed->aggregates()] // => [true, false]
 */
interface SetFunction extends Scalar
{
    /**
     * Tells whether the call aggregates the rows of its query block: it is written without OVER.
     */
    public function aggregates(): bool;
}
