<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One named field read from the affected target row.
 *
 * @visibility public
 * @example Naming the deleted target slot
 *     $field = new \SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturnedColumn(new \SqlSemantics\Platform\PostgreSql\Ir\ColumnReference(0), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('removed_id'));
 *     $field->name->value // => 'removed_id'
 */
final class ReturnedColumn
{
    use Snapshot;

    /**
     * Checks the local occurrence. The owning mutation checks the target slot.
     */
    public function __construct(public readonly ColumnReference|TextColumnReference|BooleanColumnReference|WideIntegerColumnReference $reference, public readonly Identifier $name)
    {
        Check::input($reference->occurrence === 0, 'A returned field must read the single target occurrence.');
    }
}
