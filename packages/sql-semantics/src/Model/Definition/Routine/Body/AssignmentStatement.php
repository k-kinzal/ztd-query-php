<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\AssignedUserVariable;
use SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet;
use SqlSemantics\Model\Configuration\Connection\ConnectionNames;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalAssignment;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\TriggerRowAssignment;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A SET statement in a stored program that assigns at least one local variable or NEW trigger column, in written order.
 * Other items of the same SET keep their ordinary setting and user-variable forms.
 * @visibility public
 * @example Reading mixed SET items
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) SET a = 1, @b = a');
 *     count($statement->body->assignments) // => 2
 *     $statement->body->assignments[1] instanceof \SqlSemantics\Model\Configuration\AssignedUserVariable // => true
 */
final class AssignmentStatement implements ProgramStatement
{
    /**
     * @var non-empty-list<LocalAssignment|TriggerRowAssignment|DefaultSetting|AssignedUserVariable|AssignedSetting|CurrentSetting|ConnectionNames|ConnectionCharacterSet>
     */
    public readonly array $assignments;

    /**
     * Requires at least one local variable or trigger row assignment.
     * @param list<LocalAssignment|TriggerRowAssignment|DefaultSetting|AssignedUserVariable|AssignedSetting|CurrentSetting|ConnectionNames|ConnectionCharacterSet> $assignments
     * @throws InvalidStructure
     */
    public function __construct(array $assignments)
    {
        Collections::alternatives($assignments, [LocalAssignment::class, TriggerRowAssignment::class, DefaultSetting::class, AssignedUserVariable::class, AssignedSetting::class, CurrentSetting::class, ConnectionNames::class, ConnectionCharacterSet::class]);
        if (array_filter($assignments, static fn (object $item): bool => $item instanceof LocalAssignment || $item instanceof TriggerRowAssignment) === []) {
            throw new InvalidStructure('A stored program assignment sets a local variable or trigger column.');
        }
        $this->assignments = Collections::nonEmpty($assignments);
    }
}
