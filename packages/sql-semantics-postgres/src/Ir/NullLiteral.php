<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An explicit SQL NULL value.
 *
 * @visibility public
 * @example Creating the NULL operand
 *     new \SqlSemantics\Platform\PostgreSql\Ir\NullLiteral() instanceof \SqlSemantics\Platform\PostgreSql\Ir\NullLiteral // => true
 */
final class NullLiteral
{
    use Snapshot;
}
