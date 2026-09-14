<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\PlanSyntaxException;

/**
 * A plan part is neither a relation nor a plain table name.
 */
final class InvalidTableNameException extends PlanSyntaxException
{
    /**
     * A plan part is neither a relation nor a plain table name.
     */
    public function __construct(
        public readonly string $part,
    ) {
        parent::__construct(sprintf(
            'A FixturePlan part must be a Relation or a plain table name, but "%s" is '
            . 'neither. To build a plan from relation syntax, use FixturePlan::from().',
            $part
        ));
    }
}
