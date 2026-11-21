<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An explicit truth value in a result column.
 *
 * @visibility public
 * @example Constructing TRUE
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral(true))->value // => true
 */
final class BooleanLiteral
{
    use Snapshot;

    /**
     * Stores the truth value used in the projection.
     */
    public function __construct(public readonly bool $value)
    {
    }
}
