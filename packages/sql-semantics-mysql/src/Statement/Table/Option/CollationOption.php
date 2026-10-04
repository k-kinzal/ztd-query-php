<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The default collation of the table: [DEFAULT] COLLATE.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) COLLATE = utf8mb4_bin');
 *     $create->statement->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption // => true
 */
final class CollationOption implements TableOption
{
    use Snapshot;

    /**
     * @param CollationName $collation The collation, or DEFAULT
     */
    public function __construct(public readonly CollationName $collation)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('COLLATE')->node($this->collation);
    }
}
