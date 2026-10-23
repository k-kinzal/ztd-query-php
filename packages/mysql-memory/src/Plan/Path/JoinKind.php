<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

/**
 * How a join pairs the rows of its two inputs.
 *
 * An inner join keeps the pairs that meet the condition; a left join also keeps each left row
 * that meets none, with NULL for the right columns; a right join keeps each right row so.
 *
 * @visibility MySqlMemory
 */
enum JoinKind
{
    case Inner;
    case Left;
    case Right;
}
