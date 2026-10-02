<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Statement\Node;

/**
 * An ALGORITHM or LOCK option of an online data definition statement.
 *
 * The table change family provides the structures; CREATE INDEX and DROP INDEX
 * hold them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 */
interface AlterOption extends Node
{
}
