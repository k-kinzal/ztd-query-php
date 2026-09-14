<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Exception;

use SqlFixture\Fixture\PlanSchemaException;
use SqlFixture\Plan\ColumnRef;

/**
 * A generated parent row cannot supply the referenced value.
 */
final class MissingRelationValueException extends PlanSchemaException
{
    /**
     * A generated parent row cannot supply the referenced value.
     */
    public function __construct(
        public readonly string $childColumn,
        public readonly ColumnRef $parent,
        public readonly string $parentColumn,
    ) {
        parent::__construct(sprintf(
            'Cannot fill %s: the generated %s row has no %s to copy from.',
            $childColumn,
            $parent->table,
            $parentColumn
        ));
    }
}
