<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation\Joining;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\JoinKind;
use SqlSemantics\Model\TableUse;

/**
 * The Cartesian product of two inputs, without a match predicate.
 *
 * @visibility public
 * @example Binding a Cartesian product
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT * FROM t AS a CROSS JOIN t AS b');
 *     $query->from instanceof \SqlSemantics\Model\Relation\Joining\CrossJoin // => true
 *     $query->from->kind // => \SqlSemantics\Model\JoinKind::Cross
 *     count($query->outputs) // => 4
 */
final class CrossJoin extends Join
{
    /**
     * Requires two relational inputs and fixes the operation to a Cartesian product.
     */
    public function __construct(string $id, TableUse|Join $left, TableUse|Join $right, Node $source)
    {
        parent::__construct($id, JoinKind::Cross, $left, $right, $source);
    }

    /**
     * Reconstructs this join with replacement inputs while preserving its join policy.
     */
    #[Override]
    public function withInputs(TableUse|Join $left, TableUse|Join $right): static
    {
        return new static($this->id, $left, $right, $this->source);
    }
}
