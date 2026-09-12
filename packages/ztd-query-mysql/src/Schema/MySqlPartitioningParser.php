<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Schema\TablePartitioning;

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

        $partitionBy = (new Schema\Partition\PredicateCompiler())->partitionExpression($statement->partitionBy);
        if ($partitionBy === null || !is_array($statement->partitions)) {
            return new TablePartitioning([]);
        }

        [$kind, $expression] = $partitionBy;

        return new TablePartitioning(match ($kind) {
            'RANGE' => (new Schema\Partition\PredicateCompiler())->rangePredicates($expression, $statement->partitions),
            'LIST' => (new Schema\Partition\PredicateCompiler())->listPredicates($expression, $statement->partitions),
        });
    }

}
