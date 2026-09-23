<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine;

use SqlSemantics\Dialect;
use SqlSemantics\Model\ColumnBinding;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PostgreSQL column-type reference, with its declaration when the schema resolves it.
 * @visibility public
 * @example Inspecting a referenced declaration without reading a row
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DROP FUNCTION f(t.id%TYPE)');
 *     $statement->targets[0]->parameters[0]->type->binding->column->type->name // => 'integer'
 */
final class ColumnTypeReference
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $name, public readonly ?ColumnBinding $binding)
    {
        $parts = $name->parts;
        $width = count($parts);
        if ($width < 2) {
            throw new InvalidStructure('A column-type reference requires a table and a column.');
        }
        if ($binding !== null && ($width > 3 || $parts[$width - 1] !== $binding->column->name || $parts[$width - 2] !== $binding->table->name || ($width === 3 && $parts[0] !== $binding->table->schema) || $binding->column->type->dialect !== Dialect::PostgreSql)) {
            throw new InvalidStructure('A column-type reference must identify its PostgreSQL declaration.');
        }
    }
}
