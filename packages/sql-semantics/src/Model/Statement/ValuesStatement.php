<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql;

/**
 * An explicit row source whose output types are combined by ordinal.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('VALUES(1)');
 *     $statement->rows[0][0]->symbol // => '1'
 *
 * @visibility public
 */
final class ValuesStatement extends BoundQuery
{
    /**
     * @param non-empty-list<list<Expression>> $rows Equal-width replacement rows
     */
    public function withRows(array $rows): self
    {
        return $this->clause('rows', Sql\Parts::rows($rows));
    }
}
