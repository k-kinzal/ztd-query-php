<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql;

/**
 * A DELETE with distinct affected tables, read inputs, and a row predicate.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DELETE FROM t WHERE id=1');
 *     $statement->where->symbol // => '='
 *
 * @visibility public
 */
final class DeleteStatement extends BoundStatement
{
    /**
     * Sets or removes the predicate without changing the deletion targets.
     */
    public function withWhere(?Expression $where): self
    {
        return $this->clause('where', Sql\Parts::expressions('WHERE', $where === null ? [] : [$where]));
    }
}
