<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnFormat;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The COLUMN_FORMAT attribute of a column of an NDB table.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-ndb-column-options.
 *
 * @visibility public
 * @example Reading the attribute of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT COLUMN_FORMAT FIXED)');
 *     $create->statement->elements[0]->specification->attributes[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\FormatAttribute // => true
 */
final class FormatAttribute implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param ColumnFormat $format The storage format
     */
    public function __construct(public readonly ColumnFormat $format)
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
        $out->keyword('COLUMN_FORMAT', $this->format->value);
    }
}
