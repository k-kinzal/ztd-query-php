<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\InsertMethod;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;

/**
 * The INSERT_METHOD option of a MERGE table.
 *
 * The optional equals sign is retained independently of the option value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) INSERT_METHOD = LAST');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\InsertMethodOption // => true
 */
final class InsertMethodOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param InsertMethod $method The table that receives inserted rows
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly InsertMethod $method, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('INSERT_METHOD');
        if ($this->equals) {
            $out->symbol('=');
        }
        $out->keyword($this->method->value);
    }
}
