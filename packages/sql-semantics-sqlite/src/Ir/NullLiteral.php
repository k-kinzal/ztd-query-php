<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * An explicit SQL NULL value.
 *
 * @visibility public
 * @example Creating the NULL operand
 *     new \SqlSemantics\Platform\Sqlite\Ir\NullLiteral() instanceof \SqlSemantics\Platform\Sqlite\Ir\NullLiteral // => true
 */
final class NullLiteral
{
    use Snapshot;
}
