<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Sql;

/**
 * An INSERT or REPLACE with ordered destinations, inputs, conflicts, and returned values.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t VALUES(1)');
 *     $statement->rows[0][0]->symbol // => '1'
 *
 * @visibility public
 */
final class InsertStatement extends BoundStatement
{
    /**
     * @param non-empty-list<list<Expression>> $rows Rows in destination-column order
     */
    public function withRows(array $rows): self
    {
        return $this->clause('rows', Sql\Parts::rows($rows));
    }

    /**
     * @param list<OutputColumn> $outputs
     */
    public function withReturning(array $outputs): self
    {
        $sql = $outputs === [] ? new Sql\Tree('returning', []) : new Sql\Tree('returning', [Sql\Build::keyword('RETURNING'), Sql\Parts::outputs($outputs, $this->context()->schema()->dialect)]);
        return $this->clause('returning', $sql);
    }
}
