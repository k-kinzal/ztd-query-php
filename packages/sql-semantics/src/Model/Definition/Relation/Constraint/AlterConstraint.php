<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Constraint;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\CheckingTime;

/**
 * Changes when a foreign key constraint is checked; an empty specification means NOT DEFERRABLE.
 * @visibility public
 * @example Reading the new checking time
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER CONSTRAINT fk DEFERRABLE INITIALLY DEFERRED');
 *     $statement->actions[0]->checking // => \SqlSemantics\Schema\Constraint\CheckingTime::DeferrableDeferred
 */
final class AlterConstraint implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly CheckingTime $checking)
    {
        CatalogInvariant::identifier($name);
    }
}
