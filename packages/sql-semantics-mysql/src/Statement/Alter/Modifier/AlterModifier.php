<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Modifier;

use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;

/**
 * An action of ALTER TABLE that only says how the change runs: ALGORITHM, LOCK, or WITH or WITHOUT VALIDATION.
 *
 * Only modifiers may precede a partition operation such as `ADD PARTITION`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 */
interface AlterModifier extends AlterCommand
{
}
