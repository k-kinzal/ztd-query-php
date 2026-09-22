<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use SqlParser\Parser\Node;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Distinguishes writable MySQL table references from derived read relations.
 * @visibility SqlSemantics
 */
final class DeleteTargets
{
    /**
     * @param list<TableUse> $targets Resolved or unresolved target references
     * @return list<TableReference> Named table destinations
     * @throws InvalidSql
     */
    public static function tables(array $targets, Node $source): array
    {
        return array_map(static function (TableUse $target) use ($source): TableReference {
            if (!$target instanceof TableReference) {
                throw new InvalidSql(InputViolation::DeleteTarget, $source);
            }
            return $target;
        }, $targets);
    }
}
