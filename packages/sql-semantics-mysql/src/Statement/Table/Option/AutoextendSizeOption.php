<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The AUTOEXTEND_SIZE option (MySQL 8.0.23 and later): how much InnoDB extends the tablespace file of the table at a time.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) AUTOEXTEND_SIZE = 4M');
 *     $create->statement->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\AutoextendSizeOption // => true
 */
final class AutoextendSizeOption implements TableOption
{
    use Snapshot;

    /**
     * @param ByteSize $size The size
     */
    public function __construct(public readonly ByteSize $size)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('AUTOEXTEND_SIZE')->node($this->size);
    }
}
