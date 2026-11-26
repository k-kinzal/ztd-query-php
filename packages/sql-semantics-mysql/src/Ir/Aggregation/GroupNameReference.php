<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A resolved group field with a bare name shared by another projected value.
 *
 * @visibility public
 * @example Retaining the name of a resolved group field
 *     $key = new \SqlSemantics\Platform\MySql\Ir\Aggregation\GroupNameReference(new \SqlSemantics\Platform\MySql\Ir\ColumnReference(0), new \SqlSemantics\Platform\MySql\Ir\Identifier('ID'));
 *     $key->name->value // => 'ID'
 */
final class GroupNameReference
{
    use Snapshot;

    /**
     * Retains the owning input slot and the spelling used in the native diagnostic.
     */
    public function __construct(public readonly ColumnReference $column, public readonly Identifier $name)
    {
        Check::input($column->occurrence === 0, 'A bare group reference must belong to its single input.');
    }
}
