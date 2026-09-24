<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation\Joining;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\JoinKind;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An outer join with no ON or USING clause, as accepted by SQLite.
 *
 * @visibility public
 * @example Binding an outer join without a predicate
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id FROM t a LEFT JOIN t b');
 *     $query->from instanceof \SqlSemantics\Model\Relation\Joining\UnconditionalOuterJoin // => true
 *     $query->from->kind // => \SqlSemantics\Model\JoinKind::Left
 */
final class UnconditionalOuterJoin extends Join
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(string $id, JoinKind $kind, TableUse|Join $left, TableUse|Join $right, Node $source)
    {
        if (!in_array($kind, [JoinKind::Left, JoinKind::Right, JoinKind::Full], true)) {
            throw new InvalidStructure('An unconditional outer join requires LEFT, RIGHT or FULL.');
        }
        parent::__construct($id, $kind, $left, $right, $source);
    }

    /**
     * Reconstructs this join with replacement inputs while preserving its join policy.
     */
    #[Override]
    public function withInputs(TableUse|Join $left, TableUse|Join $right): static
    {
        return new static($this->id, $this->kind, $left, $right, $this->source);
    }
}
