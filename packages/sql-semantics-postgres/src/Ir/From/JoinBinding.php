<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join as ParsedJoin;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind as ParsedKind;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinOn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\ParenthesizedJoin;

/**
 * Allocates each resolved column in its own table occurrence while retaining the parsed association.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
    public function __construct(private readonly BoundStatement $operation, private readonly ParsedJoin $join, private readonly bool $catalogFirst)
    {
        Check::input(!$join->natural && ($join->condition === null || $join->condition instanceof JoinOn), 'A joined input requires explicit ON or CROSS without a merged column list.');
        $this->left = self::bindChild($operation, $join->left, $catalogFirst);
        $this->right = self::bindChild($operation, $join->right, $catalogFirst);
        $left = $this->left instanceof self ? $this->left->inputs : [$this->left];
        $right = $this->right instanceof self ? $this->right->inputs : [$this->right];
        $this->inputs = array_merge($left, $right);
        Check::input(count($this->inputs) <= 61, 'A joined source can have at most 61 input occurrences.');
    }

    /**
     * Opens an ordinary source or a joined source, retaining transparent parentheses.
     */
    public static function bind(BoundStatement $operation, ?Relation $source, bool $catalogFirst): SourceBinding|self
    {
        $source = self::ungroup($source);

        return $source instanceof ParsedJoin ? new self($operation, $source, $catalogFirst) : new SourceBinding($operation, $source);
    }

    /**
     * Opens a joined leaf or subtree in the join's occurrence order.
     */
    public static function bindChild(BoundStatement $operation, Relation $source, bool $catalogFirst): DerivedBinding|SourceBinding|self
    {
        $input = self::ungroup($source);

        return $input instanceof DerivedTable ? new DerivedBinding($operation, $input) : self::bind($operation, $input, $catalogFirst);
    }

    /**
     * Resolves transparent relation parentheses without collapsing the join.
     */
    public static function ungroup(?Relation $source): ?Relation
    {
        while ($source instanceof ParenthesizedJoin) {
            Check::input($source->alias === null, 'An alias on a whole join is outside this source domain.');
            $source = $source->join;
        }

        return $source;
    }

    /**
     * Resolves the exact input occurrence and allocates a slot within it.
     */
    public function reference(Scalar $scalar): ColumnReference
    {
        $resolution = $this->operation->facts->scalar($scalar)->resolution;
        Check::input($resolution instanceof ResolvedColumn, 'A column must resolve within this join scope.');

        return $this->resolved($resolution);
    }

    /**
     * Allocates a checked field in the join occurrence containing its resolved input.
     */
    public function resolved(ResolvedColumn $resolution): ColumnReference
    {
        Check::input($resolution->depth === 0, 'A column must resolve within this join scope.');
        $wholeJoin = self::ungroup($resolution->relation) === $this->join;
        $expanded = null;
        foreach ($this->inputs as $occurrence => $binding) {
            $relation = $binding->relation();
            if ($relation === $resolution->relation) {
                return new ColumnReference($binding->resolved($resolution)->slot, $occurrence);
            }
            if ($wholeJoin && $relation !== null
                && in_array($resolution->slot, $this->operation->facts->relation($relation)->shape->slots, true)) {
                Check::input($expanded === null, 'An expanded join field must bind to exactly one input occurrence.');
                $bound = $binding->resolved(new ResolvedColumn($relation, $resolution->slot));
                $expanded = new ColumnReference($bound->slot, $occurrence);
            }
        }
        Check::input($expanded !== null, 'A column must resolve within this join scope.');

        return $expanded;
    }

    /**
     * Closes ON over this subtree before exporting its table occurrences.
     */
    public function source(): Join
    {
        $kind = match ($this->join->kind) {
            ParsedKind::Cross => JoinKind::Cross,
            ParsedKind::Inner => JoinKind::Inner,
            ParsedKind::Left => JoinKind::Left,
            ParsedKind::Right => JoinKind::Right,
            ParsedKind::Full => JoinKind::Full,
        };
        $on = (new PredicateLowering())->lower($this->join->condition instanceof JoinOn ? $this->join->condition->condition : null, $this, $this->catalogFirst);
        $left = $this->left->source();
        $right = $this->right->source();
        Check::invariant($left !== null && $right !== null, 'Both checked join sides have sources.');

        return new Join($kind, $left, $right, $on);
    }
}
