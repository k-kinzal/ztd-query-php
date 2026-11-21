<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An explicit SQL NULL value.
 *
 * @visibility public
 * @example Creating the NULL operand
 *     new \SqlSemantics\Platform\MySql\Ir\NullLiteral() instanceof \SqlSemantics\Platform\MySql\Ir\NullLiteral // => true
 */
final class NullLiteral
{
    use Snapshot;
}
