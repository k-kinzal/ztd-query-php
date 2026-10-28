<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;

/**
 * The ROW_FORMAT option.
 *
 * The optional equals sign is retained independently of the option value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) ROW_FORMAT = DYNAMIC');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption // => true
 */
final class RowFormatOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param RowFormat $format The row format
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly RowFormat $format, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('ROW_FORMAT');
        if ($this->equals) {
            $out->symbol('=');
        }
        $out->keyword($this->format->value);
    }
}
