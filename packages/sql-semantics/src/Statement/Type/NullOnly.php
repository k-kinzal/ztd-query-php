<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

use SqlSemantics\Statement\Snapshot;

/**
 * The type of an expression that can only be SQL NULL and has no other type yet.
 *
 * @visibility public
 * @example Typing a bare NULL
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT NULL');
 *     $operation->field(0)->type instanceof \SqlSemantics\Statement\Type\NullOnly // => true
 */
final class NullOnly implements TypeFact
{
    use Snapshot;
}
