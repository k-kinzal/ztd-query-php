<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * How a database decides the next value of a numbered column.
 *
 * Which one a column uses decides what the next value would have been, which
 * is what a rewritten INSERT has to read back in place of the number the
 * database never assigned.
 */
enum IdentityGenerationStrategy
{
    case MaxValue;
    case Sequence;
}
