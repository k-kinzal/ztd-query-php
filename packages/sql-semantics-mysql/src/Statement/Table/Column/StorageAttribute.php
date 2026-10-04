<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The STORAGE attribute of a column of an NDB table.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-ndb-column-options.
 *
 * @visibility public
 * @example Reading the attribute of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT STORAGE DISK)');
 *     $create->statement->elements[0]->specification->attributes[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\StorageAttribute // => true
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
