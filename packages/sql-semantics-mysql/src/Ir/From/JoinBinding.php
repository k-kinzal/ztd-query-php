<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable as ParsedJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;

/**
 * Allocates each resolved column in its own table occurrence while retaining the parsed association.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class JoinBinding
{
    private readonly SourceBinding|self $left;
    private readonly SourceBinding|self $right;

    /**
     * @var non-empty-list<SourceBinding>
     */
    private readonly array $inputs;

    /**
     * Binds both operands before any slot is exported.
     */
    public function __construct(private readonly BoundStatement $operation, private readonly ParsedJoin $join)
    {
        Check::input(!$join->operator->natural() && $join->using === [], 'A joined input requires explicit ON or CROSS without a merged column list.');
        $this->left = self::bind($operation, $join->left);
        $this->right = self::bind($operation, $join->right);
        $left = $this->left instanceof self ? $this->left->inputs : [$this->left];
        $right = $this->right instanceof self ? $this->right->inputs : [$this->right];
        $this->inputs = array_merge($left, $right);
        Check::input(count($this->inputs) <= 61, 'A joined source can have at most 61 input occurrences.');
    }

    /**
     * Opens an ordinary source or a joined source, retaining transparent parentheses.
     */
    public static function bind(BoundStatement $operation, ?Relation $source): SourceBinding|self
    {
        while ($source instanceof NestedRelation) {
            $source = $source->relation;
        }

        return $source instanceof ParsedJoin ? new self($operation, $source) : new SourceBinding($operation, $source);
    }

    /**
     * Resolves the exact input occurrence and allocates a slot within it.
     * @throws InvalidConstruction when the occurrence is outside the subtree
     */
    public function reference(Scalar $scalar): ColumnReference
    {
        $resolution = $this->operation->facts->scalar($scalar)->resolution;
        Check::input($resolution instanceof ResolvedColumn && $resolution->depth === 0, 'A column must resolve within this join scope.');
        foreach ($this->inputs as $occurrence => $binding) {
            if ($binding->relation() === $resolution->relation) {
                return new ColumnReference($binding->reference($scalar)->slot, $occurrence);
            }
        }

        throw new InvalidConstruction('A column must resolve within this join scope.');
    }

    /**
     * Closes ON over this subtree before exporting its table occurrences.
     */
    public function source(): Join
    {
        $on = (new PredicateLowering())->lower($this->join->on, $this);
        $left = $this->left->source();
        $right = $this->right->source();
        Check::invariant($left !== null && $right !== null, 'Both checked join sides have sources.');

        return new Join($this->kind(), $left, $right, $on);
    }

    /**
     * Fixes the effective operator in this dialect.
     * @throws InvalidConstruction when the join has no matching IR operator
     */
    private function kind(): JoinKind
    {
        return match ($this->join->operator) {
            JoinOperator::Join, JoinOperator::Cross, JoinOperator::Inner => $this->join->on === null ? JoinKind::Cross : JoinKind::Inner,
            JoinOperator::Left, JoinOperator::LeftOuter => JoinKind::Left,
            JoinOperator::Right, JoinOperator::RightOuter => JoinKind::Right,
            JoinOperator::StraightJoin, JoinOperator::Natural, JoinOperator::NaturalLeft, JoinOperator::NaturalRight,
            JoinOperator::NaturalInner, JoinOperator::NaturalLeftOuter, JoinOperator::NaturalRightOuter => throw new InvalidConstruction('The join operator has no complete MySQL representation.'),
        };
    }
}
