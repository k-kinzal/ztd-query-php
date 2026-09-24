<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Partition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Partition bound values are PostgreSQL expressions.
 * @visibility SqlSemantics
 */
final class PartitionInvariant
{
    /**
     * @param list<Expression> $values
     * @throws InvalidStructure
     */
    public static function expressions(array $values): void
    {
        foreach ($values as $value) {
            if ($value->type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('Partition bound values require the PostgreSQL dialect.');
            }
        }
    }
}
