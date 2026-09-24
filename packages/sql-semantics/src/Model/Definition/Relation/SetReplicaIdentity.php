<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects the replica identity: a fixed policy or the name of a unique index.
 * @visibility public
 * @example Selecting an index as the identity
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t REPLICA IDENTITY USING INDEX t_key');
 *     $statement->actions[0]->identity // => 't_key'
 */
final class SetReplicaIdentity implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly ReplicaIdentity|string $identity)
    {
        if (is_string($identity)) {
            CatalogInvariant::identifier($identity);
        }
    }
}
