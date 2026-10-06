<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * START TRANSACTION, the table option of CREATE TABLE ... SELECT that makes the statement atomic under row-based replication (MySQL 8.0.21 and later).
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) START TRANSACTION');
 *     $create->statement->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction // => true
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
