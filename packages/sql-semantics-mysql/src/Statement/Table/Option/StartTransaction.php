<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * START TRANSACTION, the table option of CREATE TABLE ... SELECT that makes the statement atomic under row-based replication (MySQL 8.0.21 and later).
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
 */
final class StartTransaction implements TableOption
{
    use Snapshot;

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('START', 'TRANSACTION');
    }
}
