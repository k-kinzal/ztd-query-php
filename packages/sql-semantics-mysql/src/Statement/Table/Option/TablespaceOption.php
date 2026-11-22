<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * The TABLESPACE option: the tablespace that stores the table.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
 */
final class TablespaceOption implements TableOption
{
    use Snapshot;

    /**
     * @param Name $tablespace The tablespace
     */
    public function __construct(public readonly Name $tablespace)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('TABLESPACE')->name($this->tablespace, NameUse::Label);
    }
}
