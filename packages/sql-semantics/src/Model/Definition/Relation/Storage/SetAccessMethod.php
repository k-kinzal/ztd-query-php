<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Storage;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Rewrites the relation with another table access method; null selects the server default.
 * @visibility public
 * @example Selecting the default method
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t SET ACCESS METHOD DEFAULT');
 *     $statement->actions[0]->method // => null
 */
final class SetAccessMethod implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?string $method)
    {
        if ($method !== null) {
            CatalogInvariant::identifier($method);
        }
    }
}
