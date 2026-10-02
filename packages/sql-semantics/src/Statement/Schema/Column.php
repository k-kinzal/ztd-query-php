<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Name;

/**
 * A column declaration that references can share by identity.
 * @visibility public
 * @example Supplying declared column facts
 *     $column = new \SqlSemantics\Statement\Schema\Column(new \SqlSemantics\Statement\Identifier\Name('id'), new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::Integer));
 *     $column->name->value // => 'id'
 */
final class Column
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Keeps declaration facts independently of query-local NULL extension.
     */
    public function __construct(public readonly Name $name, public readonly TypeDescriptor $type, public readonly Nullability $nullability = Nullability::MaybeNull)
    {
    }
}
