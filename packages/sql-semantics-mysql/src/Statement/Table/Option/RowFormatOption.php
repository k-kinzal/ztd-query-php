<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The ROW_FORMAT option.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) ROW_FORMAT = DYNAMIC');
 *     $create->statement->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption // => true
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
