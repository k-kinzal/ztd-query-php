<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * The default collation of the table: [DEFAULT] COLLATE.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
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
