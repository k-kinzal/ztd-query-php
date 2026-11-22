<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The ON UPDATE clause of a TIMESTAMP or DATETIME column: the current timestamp function that updates the column when another column of the row changes.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/timestamp-initialization.html.
 *
 * @visibility SqlSemantics
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
