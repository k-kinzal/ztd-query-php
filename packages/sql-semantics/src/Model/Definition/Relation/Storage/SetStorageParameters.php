<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Storage;

use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * Sets one or more storage parameters of the relation.
 * @visibility public
 * @example Reading the parameters
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t SET (fillfactor = 70, toast.autovacuum_enabled = false)');
 *     $statement->actions[0]->parameters[1]->name->parts // => ['toast', 'autovacuum_enabled']
 * @example Rejecting an empty parameter list
 *     new \SqlSemantics\Model\Definition\Relation\Storage\SetStorageParameters([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetStorageParameters implements RelationAction
{
    /**
     * @param non-empty-list<Parameter> $parameters
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $parameters)
    {
        Collections::objects(Collections::nonEmpty($parameters), Parameter::class);
    }
}
