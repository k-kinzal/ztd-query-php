<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium;

/**
 * The STORAGE attribute of a column of an NDB table.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-ndb-column-options.
 *
 * @visibility SqlSemantics
 */
final class StorageAttribute implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param StorageMedium $medium Where the column is stored
     */
    public function __construct(public readonly StorageMedium $medium)
    {
    }

    /**
     * Derives nothing: the attribute holds no expression.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the attribute.
     */
    public function render(Output $out): void
    {
        $out->keyword('STORAGE', $this->medium->value);
    }
}
