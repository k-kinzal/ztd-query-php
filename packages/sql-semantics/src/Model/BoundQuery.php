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
abstract class BoundQuery extends BoundStatement implements ResultStatement
{
    /**
     * @param list<Ordering> $orderBy
     * @visibility SqlSemantics
     */
    public function __construct(
        Statement\Origin $origin,
        public readonly ?Query\WithClause $ctes,
        public readonly array $orderBy,
        public readonly ?Expression $limit,
        public readonly ?Expression $offset,
        public readonly bool $withTies,
    ) {
        parent::__construct($origin);
        Validation\Collections::objects($orderBy, Ordering::class);
        Validation\StatementOperands::expressions([$limit, $offset], $origin->dialect);
        Validation\StatementOperands::ctes($ctes, $origin->dialect);
        foreach ($orderBy as $ordering) {
            $value = $ordering->key;
            if ($value instanceof Expression) {
                Validation\StatementOperands::expressions([$value], $origin->dialect);
            }
        }
    }

    /**
     * @param list<Ordering> $orderBy Ordered keys evaluated after projection
     */
    abstract public function withOrderBy(array $orderBy): static;
}
