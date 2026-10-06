<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Method;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * `[LINEAR] HASH (expression)`: partitioning by the value of an expression modulo the number of partitions.
 *
 * Mirrors PT_part_type_def_hash and PT_sub_partition_by_hash. The
 * expression is derived in the scope of the partitioned table.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-hash.html.
 *
 * @visibility public
 * @example Holding a hash partitioning
 *     $method = new \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod(false, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('id')));
 *     [$method->linear, $method->expression->name->value] // => [false, 'id']
 */
final class HashMethod implements PartitionMethod
{
    use Snapshot;

    /**
     * @param bool $linear Whether LINEAR is written
     * @param Scalar $expression The partitioning expression
     */
    public function __construct(public readonly bool $linear, public readonly Scalar $expression)
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
        if ($this->linear) {
            $out->keyword('LINEAR');
        }
        $out->keyword('HASH')->symbol('(')->node($this->expression)->symbol(')');
    }
}
