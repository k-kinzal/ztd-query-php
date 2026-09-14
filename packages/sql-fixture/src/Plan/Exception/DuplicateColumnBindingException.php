<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\PlanStructureException;

/**
 * A child column is bound to two different parent endpoints.
 */
final class DuplicateColumnBindingException extends PlanStructureException
{
    /**
     * A child column is bound to two different parent endpoints.
     */
    public function __construct(
        public readonly ColumnRef $child,
        public readonly ColumnRef $first,
        public readonly ColumnRef $second,
    ) {
        parent::__construct(sprintf(
            '%s is bound to %s and to %s. A column can reference one parent, so one of '
            . 'the two relations has to go.',
            $child->toString(),
            $first->toString(),
            $second->toString()
        ));
    }
}
