<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;

/**
 * The AUTOEXTEND_SIZE option (MySQL 8.0.23 and later): how much InnoDB extends the tablespace file of the table at a time.
 *
 * The optional assignment sign is retained independently of the option value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) AUTOEXTEND_SIZE = 4M');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\AutoextendSizeOption // => true
 */
final class AutoextendSizeOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ByteSize $size The size
     * @param \SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null $operator The written assignment sign, or null when omitted
     */
    public function __construct(public readonly ByteSize $size, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?\SqlSemantics\Platform\MySql\Statement\AssignmentOperator $operator = null)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('AUTOEXTEND_SIZE');
        if ($this->operator !== null) {
            $out->symbol($this->operator->value);
        }
        $out->node($this->size);
    }
}
