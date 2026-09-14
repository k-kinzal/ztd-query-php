<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\PlanSyntaxException;

/**
 * The two relation endpoints name different numbers of columns.
 */
final class CompositeArityMismatchException extends PlanSyntaxException
{
    /**
     * The two relation endpoints name different numbers of columns.
     */
    public function __construct(
        public readonly ColumnRef $left,
        public readonly ColumnRef $right,
    ) {
        parent::__construct(sprintf(
            'The relation %s ... %s names %d columns on one side and %d on the other.',
            $left->toString(),
            $right->toString(),
            count($left->columns),
            count($right->columns)
        ));
    }
}
