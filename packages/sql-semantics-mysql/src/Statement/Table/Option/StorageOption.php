<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * The STORAGE option of an NDB table: DISK or MEMORY.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
 */
final class StorageOption implements TableOption
{
    use Snapshot;

    /**
     * @param StorageMedium $medium Where the table is stored
     */
    public function __construct(public readonly StorageMedium $medium)
    {
        Check::input($medium !== StorageMedium::Default, 'A table is stored on DISK or in MEMORY.');
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('STORAGE', $this->medium->value);
    }
}
