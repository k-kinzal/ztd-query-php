<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\InsertMethod;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * The INSERT_METHOD option of a MERGE table.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
 */
final class InsertMethodOption implements TableOption
{
    use Snapshot;

    /**
     * @param InsertMethod $method The table that receives inserted rows
     */
    public function __construct(public readonly InsertMethod $method)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('INSERT_METHOD', $this->method->value);
    }
}
