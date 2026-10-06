<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The COLLATE attribute of a character column written among the other attributes.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-column.html.
 *
 * @visibility public
 * @example Reading the attribute of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a TEXT NOT NULL COLLATE utf8mb4_bin)');
 *     $create->statement->elements[0]->specification->attributes[1] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute // => true
 */
final class CollateAttribute implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param CollationName $collation The collation
     */
    public function __construct(public readonly CollationName $collation)
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
        $out->keyword('COLLATE')->node($this->collation);
    }
}
