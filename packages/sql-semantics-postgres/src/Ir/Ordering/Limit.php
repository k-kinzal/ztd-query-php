<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Ordering;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The effective row bounds of a native projection.
 *
 * @visibility public
 * @example Reading the number of skipped rows
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Ordering\Limit(8, 2))->offset // => 2
 */
final class Limit
{
    use Snapshot;

    /**
     * Validates row-count and row-offset roles against the native profile.
     */
    public function __construct(public readonly ?int $count, public readonly int $offset = 0)
    {
        Check::input($count === null || ($count >= 0 && $count <= 2147483647), 'A row count must be between zero and 2147483647.');
        Check::input($offset >= 0 && $offset <= 2147483647, 'A row offset must be between zero and 2147483647.');
        Check::input($count !== null || $offset > 0, 'An unbounded count requires a positive offset.');
    }
}
