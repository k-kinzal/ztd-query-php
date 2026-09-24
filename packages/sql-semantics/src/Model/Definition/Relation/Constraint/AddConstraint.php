<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Constraint;

use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\Check;
use SqlSemantics\Schema\Constraint\ForeignKey;
use SqlSemantics\Schema\TableConstraint;

/**
 * Adds a key, foreign key, or check constraint; NOT VALID skips checking existing rows.
 * @visibility public
 * @example Reading a deferred validation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD CONSTRAINT positive CHECK (id > 0) NOT VALID');
 *     $statement->actions[0]->constraint->name // => 'positive'
 *     $statement->actions[0]->notValid // => true
 * @example Rejecting NOT VALID on a key
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD PRIMARY KEY (id)');
 *     new \SqlSemantics\Model\Definition\Relation\Constraint\AddConstraint($statement->actions[0]->constraint, true); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AddConstraint implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly TableConstraint $constraint, public readonly bool $notValid = false)
    {
        if ($notValid && !$constraint instanceof Check && !$constraint instanceof ForeignKey) {
            throw new InvalidStructure('Only check and foreign key constraints can be added without validation.');
        }
    }
}
