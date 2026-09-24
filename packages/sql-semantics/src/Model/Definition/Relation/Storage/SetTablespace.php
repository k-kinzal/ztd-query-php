<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Storage;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Moves the relation into another tablespace.
 * @visibility public
 * @example Reading the destination
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t SET TABLESPACE fast');
 *     $statement->actions[0]->tablespace // => 'fast'
 */
final class SetTablespace implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $tablespace)
    {
        CatalogInvariant::identifier($tablespace);
    }
}
