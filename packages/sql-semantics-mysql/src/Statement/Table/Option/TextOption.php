<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * A table option whose value is a string, such as COMMENT, CONNECTION or DATA DIRECTORY.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
 */
final class TextOption implements TableOption
{
    use Snapshot;

    /**
     * @param TextOptionKind $kind The option
     * @param Text $value The string
     */
    public function __construct(public readonly TextOptionKind $kind, public readonly Text $value)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->kind->value))->node($this->value);
    }
}
