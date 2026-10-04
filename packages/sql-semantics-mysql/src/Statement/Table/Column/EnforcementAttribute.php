<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * An ENFORCED or NOT ENFORCED attribute of a column, which applies to the CHECK constraint written before it.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html.
 *
 * @visibility public
 * @example Reading the attribute of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT CHECK (a > 0) NOT ENFORCED)');
 *     $create->statement->elements[0]->specification->attributes[1]->enforced // => false
 */
final class EnforcementAttribute implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param bool $enforced Whether ENFORCED is written without NOT
     */
    public function __construct(public readonly bool $enforced)
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
        if (!$this->enforced) {
            $out->keyword('NOT');
        }
        $out->keyword('ENFORCED');
    }
}
