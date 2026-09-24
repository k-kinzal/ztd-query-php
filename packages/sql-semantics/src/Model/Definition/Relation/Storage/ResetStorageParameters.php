<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Storage;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Restores the defaults of one or more storage parameters of the relation.
 * @visibility public
 * @example Reading the reset parameter names
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t RESET (fillfactor, toast.autovacuum_enabled)');
 *     $statement->actions[0]->names[0]->parts // => ['fillfactor']
 */
final class ResetStorageParameters implements RelationAction
{
    /**
     * @param non-empty-list<QualifiedName> $names
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $names)
    {
        Collections::objects(Collections::nonEmpty($names), QualifiedName::class);
        foreach ($names as $name) {
            CatalogInvariant::name($name, 2);
        }
    }
}
