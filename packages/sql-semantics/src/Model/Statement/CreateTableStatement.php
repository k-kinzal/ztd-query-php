<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundStatement;

/**
 * A table declaration with columns, constraints, indexes, and bound expressions.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TABLE x(id INTEGER)');
 *     $statement->declarations[0]->columns[0]->type->name // => 'integer'
 *
 * @visibility public
 */
final class CreateTableStatement extends BoundStatement
{
    /**
     * Replaces a declared column, then rebinds defaults, generated values, and constraints.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withColumn(\SqlSemantics\Schema\ColumnDefinition $target, \SqlSemantics\Schema\ColumnDefinition $replacement): self
    {
        if (!in_array($target, $this->declarations[0]->columns ?? [], true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('The column does not belong to this table declaration.');
        }
        return $this->component($target->source, \SqlSemantics\Model\Sql\Source::read($replacement->source));
    }
}
