<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Storage;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Restores the defaults of attribute options of one column.
 * @visibility public
 * @example Reading the reset option names
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id RESET (n_distinct)');
 *     $statement->actions[0]->names[0]->parts // => ['n_distinct']
 */
final class ResetColumnOptions implements RelationAction
{
    /**
     * @param non-empty-list<QualifiedName> $names
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly array $names)
    {
        CatalogInvariant::identifier($column);
        Collections::objects(Collections::nonEmpty($names), QualifiedName::class);
        foreach ($names as $name) {
            CatalogInvariant::name($name, 2);
        }
    }
}
