<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Storage;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * Sets attribute options, such as statistics hints, of one column.
 * @visibility public
 * @example Reading a column option
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET (n_distinct = 100)');
 *     $statement->actions[0]->column // => 'id'
 *     $statement->actions[0]->parameters[0]->name->parts // => ['n_distinct']
 */
final class SetColumnOptions implements RelationAction
{
    /**
     * @param non-empty-list<Parameter> $parameters
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly array $parameters)
    {
        CatalogInvariant::identifier($column);
        Collections::objects(Collections::nonEmpty($parameters), Parameter::class);
    }
}
