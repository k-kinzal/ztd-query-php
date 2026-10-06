<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableChange;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Resolution\Environment;

/**
 * Derives the partitioning of a table for the statement that holds it.
 *
 * Rule: MYSQL-PARTITION-FACTS-001. The entry the table definition family
 * calls for CREATE TABLE … PARTITION BY: the partitioning functions and
 * columns are derived in the scope of the partitioned table, the partition
 * values as constants (MYSQL-PARTITIONING-001). Terminates: delegates once.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-limitations.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PartitionFacts
{
    /**
     * Derives a partitioning in the scope of its table.
     *
     * @param Environment $scope The scope whose only visible relation is the partitioned table
     */
    public function partitioning(Partitioning $partitioning, Derivation $derivation, Environment $scope): void
    {
        $partitioning->derivePartitioning($derivation, $scope);
    }
}
