<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract\Index;

/**
 * The partition role established by a table declaration.
 *
 * @visibility SqlSemantics
 */
enum TablePartitionRole
{
    case Branch;
    case Leaf;
}
