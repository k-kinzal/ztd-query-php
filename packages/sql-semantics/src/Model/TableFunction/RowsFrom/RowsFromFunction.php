<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\RowsFrom;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One function invocation of a function table, with the column definition list required when it returns record.
 * @visibility public
 * @example Reading the invocation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (generate_series(1, 3))');
 *     [$statement->from->table->functions[0]->call->spelling(), $statement->from->table->functions[0]->columns] // => ['GENERATE_SERIES', []]
 */
final class RowsFromFunction
{
    /**
     * @param Expression $call Function invocation, not evaluated by binding
     * @param list<DefinedColumn> $columns Column definition list; empty when the function's result type defines its columns
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $call, public readonly array $columns = [])
    {
        Collections::objects($columns, DefinedColumn::class);
        $names = array_map(static fn (DefinedColumn $column): string => $column->name, $columns);
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidStructure('A column definition list requires unique column names.');
        }
    }
}
