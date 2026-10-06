<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Column;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * `FIRST` or `AFTER column`: where an added or changed column goes in the column order of the table.
 *
 * Without a position an added column goes last and a changed column stays
 * where it is.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-redefine-column.
 *
 * @visibility public
 * @example Placing a column after another
 *     (new \SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition(new \SqlSemantics\Statement\Identifier\Name('id')))->after?->value // => 'id'
 */
final class ColumnPosition implements Node
{
    use Snapshot;

    /**
     * @param Name|null $after The column the column follows, or null for FIRST
     */
    public function __construct(public readonly ?Name $after)
    {
    }

    /**
     * Writes the position.
     */
    public function render(Output $out): void
    {
        if ($this->after === null) {
            $out->keyword('FIRST');
        } else {
            $out->keyword('AFTER')->name($this->after, NameUse::Column);
        }
    }
}
