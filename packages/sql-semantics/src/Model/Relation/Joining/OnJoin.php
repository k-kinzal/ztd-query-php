<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation\Joining;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\JoinKind;
use SqlSemantics\Model\TableUse;

/**
 * A join with a required ON predicate, evaluated before NULL extension.
 *
 * @visibility public
  * @example Inspecting OnJoin
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT q.a, q.b FROM (t a JOIN t b ON a.id=b.id) AS q(a,b)');
 *     $alias = $query->relations[0];
 *     $inner = $alias->input;
 *     $inner instanceof \SqlSemantics\Model\Relation\Joining\OnJoin // => true
 */
final class OnJoin extends Join
{
    /**
     * Requires two relational inputs and the ON predicate evaluated before NULL extension.
     * @param bool $straight Whether MySQL STRAIGHT_JOIN fixes the left input to be read first
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(string $id, JoinKind $kind, TableUse|Join $left, TableUse|Join $right, public readonly Expression $condition, Node $source, public readonly bool $straight = false)
    {
        if ($straight && $kind !== JoinKind::Inner) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('STRAIGHT_JOIN is an inner join.');
        }
        parent::__construct($id, $kind, $left, $right, $source);
    }

    /**
     * Reconstructs this join with replacement inputs while preserving its join policy.
     */
    #[Override]
    public function withInputs(TableUse|Join $left, TableUse|Join $right): static
    {
        return new static($this->id, $this->kind, $left, $right, $this->condition, $this->source, $this->straight);
    }
}
