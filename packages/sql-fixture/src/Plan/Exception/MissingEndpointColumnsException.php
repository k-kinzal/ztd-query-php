<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\PlanSyntaxException;

/**
 * A relation endpoint names no columns.
 */
final class MissingEndpointColumnsException extends PlanSyntaxException
{
    /**
     * A relation endpoint names no columns.
     */
    public function __construct(
        public readonly string $table,
    ) {
        parent::__construct(sprintf('The endpoint for table %s names no columns.', $table));
    }
}
