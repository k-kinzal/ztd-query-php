<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

/**
 * A query result with ordered outputs, ordering, and pagination.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
 *     $statement->scopeId // => 's0'
 *
 * @visibility public
 */
abstract class BoundQuery extends BoundStatement
{
    /**
     * @param list<Ordering> $orderBy Ordered keys evaluated after projection
     */
    public function withOrderBy(array $orderBy): static
    {
        return $this->clause('orderBy', Sql\Parts::ordering($orderBy));
    }
}
