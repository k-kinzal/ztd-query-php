<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Type;

use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The type of an expression that can only be SQL NULL and has no other type yet.
 *
 * @visibility public
 * @example Typing a bare NULL
 *     $operation = (new \SqlSemantics\Platform\MySql\Semantics())->analyze('SELECT NULL');
 *     $operation->field(0)->type instanceof \SqlSemantics\Platform\MySql\Model\Statement\Type\NullOnly // => true
 */
final class NullOnly implements TypeFact
{
    use Snapshot;
}
