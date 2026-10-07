<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

/**
 * The values a system variable takes, which decide how an assignment is checked and how the value reads.
 *
 * A Boolean reads as 0 or 1 and takes ON, OFF, TRUE, FALSE, 0 and 1; an Integer reads as a
 * BIGINT within its bounds; an Enumeration takes one of its names or their position; a Set takes
 * a comma-separated list of its names; a Text takes any string.
 *
 * @visibility MySqlMemory
 */
enum Shape
{
    case Boolean;
    case Integer;
    case Unsigned;
    case Double;
    case Enumeration;
    case Set;
    case Text;
}
