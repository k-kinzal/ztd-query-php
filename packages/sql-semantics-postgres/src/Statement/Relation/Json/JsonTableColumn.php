<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation\Json;

use SqlSemantics\Statement\Node;

/**
 * One column definition of JSON_TABLE: FOR ORDINALITY, a value column, an EXISTS column, or NESTED columns.
 *
 * Mirrors PostgreSQL's `JsonTableColumn` with its `JsonTableColumnType`;
 * each kind is its own class because the operands differ.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-TABLE.
 *
 * @visibility public
 * @example Naming the contract of a JSON_TABLE column
 *     interface_exists(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonTableColumn::class) // => true
 */
interface JsonTableColumn extends Node
{
}
