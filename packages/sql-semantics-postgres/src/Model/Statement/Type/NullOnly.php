<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Type;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The type of an expression that can only be SQL NULL and has no other type yet.
 *
 * @visibility SqlSemantics
 * @example Representing a value whose only possible value is NULL
 *     (new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\NullOnly()) instanceof \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeFact // => true
 */
final class NullOnly implements TypeFact
{
    use Snapshot;
}
