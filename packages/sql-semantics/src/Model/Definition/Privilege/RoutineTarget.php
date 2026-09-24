<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One named function or procedure as a privilege level.
 * @visibility public
 * @example Reading a routine privilege level
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GRANT EXECUTE ON PROCEDURE app.p TO u');
 *     [$statement->target->kind->value, $statement->target->name->parts] // => ['PROCEDURE', ['app', 'p']]
 */
final class RoutineTarget
{
    /**
     * Requires a local or database-qualified routine name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $name, public readonly RoutineKind $kind)
    {
        if (count($name->parts) > 2) {
            throw new InvalidStructure('A routine privilege level requires a local or database-qualified name.');
        }
    }
}
