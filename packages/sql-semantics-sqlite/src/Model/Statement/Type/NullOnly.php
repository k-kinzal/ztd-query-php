<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Type;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The type of an expression that can only be SQL NULL and has no other type yet.
 *
 * @visibility public
 * @example Typing a bare NULL
 *     $operation = (new \SqlSemantics\Platform\Sqlite\Semantics())->inspect('SELECT NULL');
 *     $operation->field(0)->type instanceof \SqlSemantics\Platform\Sqlite\Model\Statement\Type\NullOnly // => true
 */
final class NullOnly implements TypeFact
{
    use Snapshot;
}
