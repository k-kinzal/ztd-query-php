<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Foreign;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The wrapper options declared on one column of a foreign table.
 * @visibility public
 * @example Reading a column option
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE ft (a integer OPTIONS (column_name 'remote_a')) SERVER s");
 *     $statement->columnOptions[0]->column // => 'a'
 *     $statement->columnOptions[0]->options[0]->name // => 'column_name'
 */
final class ColumnForeignOptions
{
    /**
     * @param non-empty-list<ForeignOption> $options
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly array $options)
    {
        CatalogInvariant::identifier($column);
        Collections::objects(Collections::nonEmpty($options), ForeignOption::class);
    }
}
