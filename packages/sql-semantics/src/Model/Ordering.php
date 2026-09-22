<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

/**
 * A result ordering expression with explicit direction and NULL placement.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
 *     $statement->orderBy[0]->descending // => true
 *
 * @visibility public
 */
final class Ordering
{
    /**
     * @param Expression|Query\Ordering\OutputPosition|Query\Ordering\OutputAlias|Query\Ordering\UnresolvedOutputPosition $key Sort value or projected output reference
     * @param bool $descending Descending order
     * @param bool|null $nullsFirst Explicit NULLS FIRST/LAST; null uses the dialect default
     */
    public function __construct(
        public readonly Expression|Query\Ordering\OutputPosition|Query\Ordering\OutputAlias|Query\Ordering\UnresolvedOutputPosition $key,
        public readonly bool $descending = false,
        public readonly ?bool $nullsFirst = null,
    ) {
    }
}
