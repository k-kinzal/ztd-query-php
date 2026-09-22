<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlParser\Parser\Node;

/**
 * Two relation inputs combined by a concrete join form.
 *
 * @example An ON join retains its predicate
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id FROM users a LEFT JOIN users b ON a.id = b.id');
 *     $statement->from instanceof \SqlSemantics\Model\Relation\Joining\OnJoin // => true
 *
 * @visibility public
 */
abstract class Join
{
    /**
     * @param string $id Query-local join identity
     * @param JoinKind $kind Logical join operation
     * @param TableUse|Join $left Left input
     * @param TableUse|Join $right Right input
     * @param Node $source Original join syntax
     * @visibility SqlSemantics
     */
    public function __construct(
        public readonly string $id,
        public readonly JoinKind $kind,
        public readonly TableUse|self $left,
        public readonly TableUse|self $right,
        public readonly Node $source,
    ) {
    }

    /**
     * Replaces both inputs while retaining this join's concrete matching operation.
     */
    abstract public function withInputs(TableUse|self $left, TableUse|self $right): static;
}
