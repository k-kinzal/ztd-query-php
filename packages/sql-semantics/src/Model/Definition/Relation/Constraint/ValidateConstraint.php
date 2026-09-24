<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Constraint;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks existing rows against a constraint that was added as NOT VALID.
 * @visibility public
 * @example Reading the validated constraint
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t VALIDATE CONSTRAINT positive');
 *     $statement->actions[0]->name // => 'positive'
 */
final class ValidateConstraint implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name)
    {
        CatalogInvariant::identifier($name);
    }
}
