<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Schema\Partition;

use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Schema\Partition\TablePartitioning;

/**
 * Converts MySQL RANGE and LIST partition metadata into row predicates.
 */
final class MySqlPartitioningParser
{
    /**
     * Parse for the supplied MySQL input.
     */
    public function parse(CreateStatement $statement): ?TablePartitioning
    {
        if ($statement->partitionBy === null) {
            return null;
        }

        $partitionBy = (new PredicateCompiler())->partitionExpression($statement->partitionBy);
        if ($partitionBy === null || !is_array($statement->partitions)) {
            return new TablePartitioning([]);
        }

        [$kind, $expression] = $partitionBy;

        return new TablePartitioning(match ($kind) {
            'RANGE' => (new PredicateCompiler())->rangePredicates($expression, $statement->partitions),
            'LIST' => (new PredicateCompiler())->listPredicates($expression, $statement->partitions),
        });
    }

}
