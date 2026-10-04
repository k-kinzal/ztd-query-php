<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;

/**
 * `PARTITION BY …` or `REMOVE PARTITIONING`: the last action of ALTER TABLE, written without a comma before it.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 */
interface TrailingCommand extends AlterCommand
{
}
