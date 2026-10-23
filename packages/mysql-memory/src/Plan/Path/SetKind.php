<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

/**
 * The set operation that combines two queries.
 *
 * @visibility MySqlMemory
 */
enum SetKind
{
    case Union;
    case Intersect;
    case Except;
}
