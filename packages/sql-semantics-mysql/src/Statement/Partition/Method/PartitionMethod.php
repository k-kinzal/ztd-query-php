<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Method;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;

/**
 * How rows are assigned to partitions or subpartitions: KEY, HASH, RANGE or LIST, over an expression or over columns.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-types.html.
 */
interface PartitionMethod extends Node
{
    /**
     * Derives the expression of the method, or checks its column names, in the scope of the partitioned table.
     *
     * @param Derivation $derivation The derivation of the statement that holds the method
     * @param Environment $scope The scope whose only visible relation is the partitioned table
     */
    public function deriveMethod(Derivation $derivation, Environment $scope): void;
}
