<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Reference\TriggerColumn;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * SET NEW.column = expression in a BEFORE trigger: changes the row about to be written.
 * @visibility public
 * @example Reading an assigned trigger column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.n = 1');
 *     $statement->body->assignments[0]->target->binding->column->name // => 'n'
 */
final class TriggerRowAssignment
{
    /**
     * Requires a NEW row column, resolved or named in an unresolved subject table, and a MySQL value.
     * @throws InvalidStructure
     */
    public function __construct(public readonly TriggerColumn|UnresolvedColumnReference $target, public readonly Expression $value)
    {
        $new = $target instanceof TriggerColumn ? $target->version === RowVersion::New : count($target->name) === 2 && strcasecmp($target->name[0], 'NEW') === 0;
        if (!$new || $value->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A trigger row assignment sets a NEW column to a MySQL expression.');
        }
    }
}
