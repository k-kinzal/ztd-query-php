<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Method;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * `RANGE (expression)` or `LIST (expression)`: partitioning by the value of an expression compared with the partition values.
 *
 * Mirrors PT_part_type_def_range_expr and PT_part_type_def_list_expr. The
 * expression is derived in the scope of the partitioned table.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-range.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-list.html.
 *
 * @visibility public
 * @example Holding a range partitioning by an expression
 *     $method = new \SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod(\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::Range, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('id')));
 *     $method->kind // => \SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::Range
 */
final class ExpressionMethod implements PartitionMethod
{
    use Snapshot;

    /**
     * @param PartitionKind $kind RANGE or LIST
     * @param Scalar $expression The partitioning expression
     */
    public function __construct(public readonly PartitionKind $kind, public readonly Scalar $expression)
    {
    }

    /**
     * Derives the expression in the scope of the table.
     */
    public function deriveMethod(Derivation $derivation, Environment $scope): void
    {
        $derivation->scalar($this->expression, $scope);
    }

    /**
     * Writes the method.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->symbol('(')->node($this->expression)->symbol(')');
    }
}
