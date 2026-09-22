<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundQuery;

/**
 * A TABLE query exposing the declared relation columns in order.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('TABLE t');
 *     $statement->outputs[0]->name // => 'id'
 *
 * @visibility public
 */
final class TableStatement extends BoundQuery
{
    /**
     * Changes the relation and derives its new output columns.
     * @param list<string> $name Qualified table name without SQL quotes
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withTable(array $name): self
    {
        if ($name === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A TABLE query requires a relation name.');
        }
        return $this->clause('table', \SqlSemantics\Model\Sql\Build::identifier($name, $this->context()->schema()->dialect));
    }
}
