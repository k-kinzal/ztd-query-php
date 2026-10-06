<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;

/**
 * The partitioning of a table: PARTITION BY with its function, columns, counts and partition definitions.
 *
 * CREATE TABLE and ALTER TABLE hold it. The statement that holds it derives
 * it in the scope of the table it partitions.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning.html.
 */
interface Partitioning extends Node
{
    /**
     * Derives the expressions of the partitioning: the partitioning functions in the scope of the table, the partition values as constants.
     *
     * @param Derivation $derivation The derivation of the statement that holds the partitioning
     * @param Environment $scope The scope whose only visible relation is the partitioned table
     */
    public function derivePartitioning(Derivation $derivation, Environment $scope): void;
}
