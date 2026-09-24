<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Reference;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Write\Storage;

/**
 * Narrows a bound destination into an exclusively writable storage path.
 *
 * @visibility SqlSemantics
 */
final class StoragePathBinder
{
    /**
     * Diagnoses a destination the grammar accepts but no storage can receive, such as a whole-row expansion.
     * @throws InvalidSql
     */
    public static function bind(Expression $expression): Storage\Path
    {
        return match (true) {
            $expression instanceof Reference\ColumnReference,
            $expression instanceof Reference\UnresolvedColumnReference => new Storage\ColumnPath($expression),
            $expression instanceof Reference\FieldAccess => new Storage\FieldPath(self::bind($expression->base), $expression->field),
            $expression instanceof Reference\ElementAccess => new Storage\ElementPath(self::bind($expression->base), $expression->index),
            $expression instanceof Reference\SliceAccess => new Storage\SlicePath(self::bind($expression->base), $expression->lower, $expression->upper),
            default => throw new InvalidSql(InputViolation::AssignmentDestination, $expression->source),
        };
    }
}
