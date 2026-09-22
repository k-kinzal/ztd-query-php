<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql;
use SqlSemantics\Model\Write\Assignment;

/**
 * An UPDATE with explicit write destinations and independently bound read inputs.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('UPDATE t SET id=2');
 *     $statement->writes[0]->value->symbol // => '2'
 *
 * @visibility public
 */
final class UpdateStatement extends BoundStatement
{
    /**
     * Sets or removes the row predicate.
     */
    public function withWhere(?Expression $where): self
    {
        return $this->clause('where', Sql\Parts::expressions('WHERE', $where === null ? [] : [$where]));
    }

    /**
     * @param non-empty-list<Assignment> $writes Assignments in SQL evaluation order
     */
    public function withAssignments(array $writes): self
    {
        \SqlSemantics\Model\Validation\Collections::objects($writes, Assignment::class);
        $items = array_map(static fn (Assignment $write): Sql\Tree => new Sql\Tree('assignment', [count($write->targets) === 1 ? $write->targets[0]->sql : Sql\Build::parentheses(Sql\Build::separated(array_map(static fn (Expression $target): Sql\Tree => $target->sql, $write->targets))), Sql\Build::keyword('='), $write->value->sql]), $writes);
        return $this->clause('writes', Sql\Build::separated($items));
    }
}
