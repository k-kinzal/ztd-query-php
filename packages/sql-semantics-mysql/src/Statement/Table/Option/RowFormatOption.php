<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * The ROW_FORMAT option.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
 */
final class RowFormatOption implements TableOption
{
    use Snapshot;

    /**
     * @param RowFormat $format The row format
     */
    public function __construct(public readonly RowFormat $format)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('ROW_FORMAT', $this->format->value);
    }
}
