<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;

/**
 * An action of ALTER TABLE that must be the only one, after modifiers at most: a partition operation or a tablespace operation.
 *
 * Mirrors PT_alter_table_standalone_action.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 */
interface StandaloneCommand extends AlterCommand
{
}
