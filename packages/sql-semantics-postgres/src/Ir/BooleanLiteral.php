<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A resolved PostgreSQL truth value.
 *
 * @visibility public
 * @example Constructing TRUE
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral(true))->value // => true
 */
final class BooleanLiteral
{
    use Snapshot;

    /**
     * Stores the native Boolean value.
     */
    public function __construct(public readonly bool $value)
    {
    }
}
