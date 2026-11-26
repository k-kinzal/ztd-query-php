<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable as ParsedJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;

/**
 * Allocates each resolved column in its own table occurrence while retaining the parsed association.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class JoinBinding
{
    private readonly DerivedBinding|SourceBinding|self $left;
    private readonly DerivedBinding|SourceBinding|self $right;

    /**
     * @var non-empty-list<DerivedBinding|SourceBinding>
     */
    private readonly array $inputs;

    /**
     * Binds both operands before any slot is exported.
     */
    public function __construct(private readonly BoundStatement $operation, private readonly ParsedJoin $join)
    {
        $this->left = self::bindChild($operation, $join->left);
        $this->right = self::bindChild($operation, $join->right);
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
        $source = self::ungroup($source);
        if (!$source instanceof TableList) {
            return $source instanceof ParsedJoin ? new self($operation, $source) : new SourceBinding($operation, $source);
        }

        $members = $source->members;
        Check::input(count($members) <= 61, 'A FROM list can have at most 61 input occurrences.');
        $input = $members[0];
        foreach (array_slice($members, 1) as $member) {
            $input = new ParsedJoin($input, JoinOperator::Cross, $member);
        }
        Check::invariant($input instanceof ParsedJoin, 'A FROM list contains at least two inputs.');

        return new self($operation, $input);
    }

    /**
     * Opens a joined leaf or subtree in the join's occurrence order.
     */
    public static function bindChild(BoundStatement $operation, Relation $source): DerivedBinding|SourceBinding|self
    {
        $input = self::ungroup($source);

        return $input instanceof DerivedTable ? new DerivedBinding($operation, $input) : self::bind($operation, $input);
    }

    /**
     * Resolves transparent relation parentheses without collapsing the join.
     */
    public static function ungroup(?Relation $source): ?Relation
    {
        while ($source instanceof NestedRelation) {
            $source = $source->relation;
        }

        return $source;
    }

    /**
     * Resolves the exact input occurrence and allocates a slot within it.
     * @throws InvalidConstruction when the occurrence is outside the subtree
     */
    public function reference(Scalar $scalar): ColumnReference
    {
        $resolution = $this->operation->facts->scalar($scalar)->resolution;
        Check::input($resolution instanceof ResolvedColumn, 'A column must resolve within this join scope.');

        return $this->resolved($resolution);
    }

    /**
     * Resolves a signed read in the exact owning join input.
     * @throws InvalidConstruction When the field is outside the signed input domain
     */
    public function membershipReference(Scalar $scalar): ColumnReference|IntegralColumnReference
    {
        $resolution = $this->operation->facts->scalar($scalar)->resolution;
        Check::input($resolution instanceof ResolvedColumn && $resolution->depth === 0, 'A membership column must resolve within this join scope.');
        foreach ($this->inputs as $occurrence => $binding) {
            if ($binding->relation() === $resolution->relation) {
                $field = $binding instanceof SourceBinding ? $binding->membershipSlot($resolution) : $binding->resolved($resolution);
                return match (true) {
                    $field instanceof ColumnReference => new ColumnReference($field->slot, $occurrence),
                    $field instanceof IntegralColumnReference => new IntegralColumnReference($field->slot, $occurrence),
                    $field instanceof TextColumnReference => throw new InvalidConstruction('A membership column requires signed integral storage.'),
                };
            }
        }

        throw new InvalidConstruction('A membership column must resolve within this join scope.');
    }

    /**
     * Resolves the exact native comparison field in a visible occurrence.
     * @throws InvalidConstruction When the field does not belong to this scope
     */
    public function comparisonField(Scalar $expression): ColumnReference|IntegralColumnReference|TextColumnReference
    {
        $resolution = $this->operation->facts->scalar($expression)->resolution;
        Check::input($resolution instanceof ResolvedColumn && $resolution->depth === 0, 'A comparison field must resolve within its join scope.');
        foreach ($this->inputs as $occurrence => $binding) {
            if ($binding->relation() === $resolution->relation) {
                $field = $binding instanceof SourceBinding ? $binding->comparisonSlot($resolution) : $binding->resolved($resolution);

                return match (true) {
                    $field instanceof ColumnReference => new ColumnReference($field->slot, $occurrence),
                    $field instanceof IntegralColumnReference => new IntegralColumnReference($field->slot, $occurrence),
                    $field instanceof TextColumnReference => new TextColumnReference($field->slot, $occurrence),
                };
            }
        }

        throw new InvalidConstruction('A comparison field must resolve within its join scope.');
    }

    /**
     * Resolves a scalar field with the native kind required by a typed read.
     */
    public function field(Scalar $scalar): ColumnReference|TextColumnReference
    {
        $resolution = $this->operation->facts->scalar($scalar)->resolution;
        Check::input($resolution instanceof ResolvedColumn, 'A column must resolve within this join scope.');

        return $this->projected($resolution);
    }

    /**
     * Allocates a checked field in the join occurrence containing its resolved input.
     *
     * @throws InvalidConstruction When the field is outside the checked input scope
     */
    public function resolved(ResolvedColumn $resolution): ColumnReference
    {
        $field = $this->projected($resolution);
        Check::input($field instanceof ColumnReference, 'The bound column must have the signed 32-bit integer type.');

        return $field;
    }

    /**
     * Resolves the source occurrence and native slot used in a projection.
     * @throws InvalidConstruction When the field is outside the checked input scope
     */
    public function projected(ResolvedColumn $resolution): ColumnReference|TextColumnReference
    {
        Check::input($resolution->depth === 0, 'A column must resolve within this join scope.');
        foreach ($this->inputs as $occurrence => $binding) {
            if ($binding->relation() === $resolution->relation) {
                $field = $binding instanceof SourceBinding ? $binding->projected($resolution) : $binding->resolved($resolution);

                return $field instanceof TextColumnReference ? new TextColumnReference($field->slot, $occurrence)
                    : new ColumnReference($field->slot, $occurrence);
            }
        }

        throw new InvalidConstruction('A column must resolve within this join scope.');
    }

    /**
     * Closes ON over this subtree before exporting its table occurrences.
     * @throws InvalidConstruction when the join has no matching IR operator
     */
    public function source(): Join
    {
        $on = $this->predicate();
        $kind = match ($this->join->operator) {
            JoinOperator::Join, JoinOperator::Cross, JoinOperator::Inner, JoinOperator::Natural, JoinOperator::NaturalInner
                => $on === null ? JoinKind::Cross : JoinKind::Inner,
            JoinOperator::Left, JoinOperator::LeftOuter, JoinOperator::NaturalLeft, JoinOperator::NaturalLeftOuter => JoinKind::Left,
            JoinOperator::Right, JoinOperator::RightOuter, JoinOperator::NaturalRight, JoinOperator::NaturalRightOuter => JoinKind::Right,
            JoinOperator::StraightJoin => throw new InvalidConstruction('The join operator has no complete MySQL representation.'),
        };
        $left = $this->left->source();
        $right = $this->right->source();
        Check::invariant($left !== null && $right !== null, 'Both checked join sides have sources.');

        return new Join($kind, $left, $right, $on);
    }

    /**
     * Resolves the explicit or merged condition of this join.
     */
    public function predicate(): BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|LogicalPredicate|NullLiteral|NullTest|TruthTest|null
    {
        if (!$this->join->operator->natural() && $this->join->using === []) {
            return (new PredicateLowering())->lower($this->join->on, $this);
        }
        Check::input($this->left instanceof SourceBinding && $this->right instanceof SourceBinding, 'A merged join requires two ordinary base-table inputs.');

        return (new MergedJoinLowering())->predicate($this->join, $this->left, $this->right);
    }
}
