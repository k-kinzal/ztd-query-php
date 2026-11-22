<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * The default character set of the table: [DEFAULT] CHARACTER SET or CHARSET.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
 */
final class CharsetOption implements TableOption
{
    use Snapshot;

    /**
     * @param CharsetName $charset The character set, or DEFAULT
     */
    public function __construct(public readonly CharsetName $charset)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('CHARSET')->node($this->charset);
    }
}
