<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;

/**
 * The STORAGE option of an NDB table: DISK or MEMORY.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) STORAGE DISK');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\StorageOption // => true
 */
final class StorageOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param StorageMedium $medium Where the table is stored
     */
    public function __construct(public readonly StorageMedium $medium, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($medium !== StorageMedium::Default, 'A table is stored on DISK or in MEMORY.');
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('STORAGE', $this->medium->value);
    }
}
