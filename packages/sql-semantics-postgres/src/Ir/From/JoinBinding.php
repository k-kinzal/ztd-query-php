<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join as ParsedJoin;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinOn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\ParenthesizedJoin;

/**
 * Allocates each resolved column in its own table occurrence while retaining the parsed association.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
    public function __construct(private readonly BoundStatement $operation, private readonly ParsedJoin $join, private readonly bool $catalogFirst)
    {
        Check::input(!$join->natural && ($join->condition === null || $join->condition instanceof JoinOn), 'A joined input requires explicit ON or CROSS without a merged column list.');
        $this->left = self::bind($operation, $join->left, $catalogFirst);
        $this->right = self::bind($operation, $join->right, $catalogFirst);
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
        while ($source instanceof ParenthesizedJoin) {
            Check::input($source->alias === null, 'An alias on a whole join is outside this source domain.');
            $source = $source->join;
        }

        return $source instanceof ParsedJoin ? new self($operation, $source, $catalogFirst) : new SourceBinding($operation, $source);
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
        $on = (new PredicateLowering())->lower($this->join->condition instanceof JoinOn ? $this->join->condition->condition : null, $this, $this->catalogFirst);
        $left = $this->left->source();
        $right = $this->right->source();
        Check::invariant($left !== null && $right !== null, 'Both checked join sides have sources.');

        return new Join($this->kind(), $left, $right, $on);
    }

    /**
     * Fixes the effective operator in this dialect.
     */
    private function kind(): JoinKind
    {
        return JoinKind::from($this->join->kind->value);
    }
}
