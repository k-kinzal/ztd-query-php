<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The ON UPDATE clause of a TIMESTAMP or DATETIME column: the current timestamp function that updates the column when another column of the row changes.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/timestamp-initialization.html.
 *
 * @visibility public
 * @example Reading the clause of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');
 *     $create->statement->elements[0]->specification->attributes[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\OnUpdate // => true
 */
final class OnUpdate implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param Scalar $value The value
     */
    public function __construct(public readonly Scalar $value)
    {
    }

    /**
     * Derives the value inside the table definition.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
        $derivation->scalar($this->value, $scope);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON', 'UPDATE')->node($this->value);
    }
}
