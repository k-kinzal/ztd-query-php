<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\RowsFrom;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PostgreSQL function table: the rows of several functions joined side by side, the shorter results padded with NULL, optionally numbered by WITH ORDINALITY.
 * A single function followed by WITH ORDINALITY binds to the same structure as ROWS FROM with that function.
 * @visibility public
 * @example Reading the functions and ordinality
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f(1), g(2)) WITH ORDINALITY');
 *     [count($statement->from->table->functions), $statement->from->table->ordinality] // => [2, true]
 */
final class RowsFromTable
{
    /**
     * @param non-empty-list<RowsFromFunction> $functions Invocations in written order
     * @param bool $ordinality Whether a bigint row number column follows the function columns
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $functions, public readonly bool $ordinality = false)
    {
        Collections::objects(Collections::nonEmpty($functions), RowsFromFunction::class);
        foreach ($functions as $function) {
            if ($function->call->type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('A function table requires PostgreSQL invocations.');
            }
        }
    }
}
