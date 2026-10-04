<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The default character set of the table: [DEFAULT] CHARACTER SET or CHARSET.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) CHARSET = utf8mb4');
 *     $create->statement->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption // => true
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
