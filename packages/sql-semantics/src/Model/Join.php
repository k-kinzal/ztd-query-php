<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlParser\Parser\Node;

/**
 * A join with its own match predicate and NULL extension identity.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
 *     $statement->from->kind->value // => 'left'
 *
 * @visibility public
 */
final class Join
{
    /**
     * @param string $id Query-local join identity
     * @param JoinKind $kind Join operation
     * @param TableUse|Join $left Left input
     * @param TableUse|Join $right Right input
     * @param Expression|null $condition Match predicate, evaluated before this join extends NULLs
     * @param Node $source Original join syntax
     */
    public function __construct(
        public readonly string $id,
        public readonly JoinKind $kind,
        public readonly TableUse|self $left,
        public readonly TableUse|self $right,
        public readonly ?Expression $condition,
        public readonly Node $source,
    ) {
    }
}
