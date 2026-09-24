<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Key;

use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Schema\TableConstraint;

/**
 * Adds a PRIMARY KEY, UNIQUE, FOREIGN KEY, or CHECK constraint to the altered table.
 * @visibility public
 * @example Reading the added constraint
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ADD CONSTRAINT pk PRIMARY KEY (id)');
 *     $statement->alterations[0]->constraint instanceof \SqlSemantics\Schema\Constraint\PrimaryKey // => true
 *     $statement->alterations[0]->constraint->name // => 'pk'
 */
final class AddConstraint implements TableAlteration
{
    /**
     * Records one integrity constraint.
     */
    public function __construct(public readonly TableConstraint $constraint)
    {
    }
}
