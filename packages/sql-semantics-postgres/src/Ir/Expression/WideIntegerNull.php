<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An explicitly typed native bigint NULL in a signed comparison.
 *
 * @visibility public
 * @example Constructing a NULL with a fixed signed width
 *     new \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerNull() instanceof \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerNull // => true
 */
final class WideIntegerNull
{
    use Snapshot;
}
