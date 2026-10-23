<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

/**
 * Selects the rows a materialized set contributes to FOUND_ROWS.
 *
 * @visibility MySqlMemory
 */
enum SetCount
{
    case None;
    case Materialized;
    case RightInput;
}
