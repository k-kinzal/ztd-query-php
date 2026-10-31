<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;

/**
 * START TRANSACTION, the table option of CREATE TABLE ... SELECT that makes the statement atomic under row-based replication (MySQL 8.0.21 and later).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) START TRANSACTION');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction // => true
 */
final class StartTransaction implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('START', 'TRANSACTION');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }
}
