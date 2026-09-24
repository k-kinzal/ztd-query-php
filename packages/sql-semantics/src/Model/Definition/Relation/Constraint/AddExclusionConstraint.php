<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Constraint;

use SqlSemantics\Model\Definition\RelationAction;

/**
 * Adds an exclusion constraint, which is always validated against existing rows.
 * @visibility public
 * @example Reading the added constraint
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD CONSTRAINT ex EXCLUDE (id WITH =)');
 *     $statement->actions[0]->constraint->name // => 'ex'
 */
final class AddExclusionConstraint implements RelationAction
{
    /**
     * The constraint is the complete operand.
     */
    public function __construct(public readonly ExclusionConstraint $constraint)
    {
    }
}
