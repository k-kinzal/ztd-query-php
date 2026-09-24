<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\RowsFrom;

use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * One entry of a column definition list, naming and typing a column of a function that returns record.
 * @visibility public
 * @example Reading a defined column
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f() AS (a integer))');
 *     [$statement->from->table->functions[0]->columns[0]->name, $statement->from->table->functions[0]->columns[0]->type->name] // => ['a', 'integer']
 */
final class DefinedColumn
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly TypeDescriptor $type)
    {
        if ($name === '') {
            throw new InvalidStructure('A defined column requires a nonempty name.');
        }
    }
}
