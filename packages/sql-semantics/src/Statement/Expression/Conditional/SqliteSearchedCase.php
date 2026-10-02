<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Conditional;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;

/**
 * A lazy choice using SQLite truth conversion on each WHEN expression in order.
 * @visibility public
 * @example Preserving searched CASE rather than constructing equality comparisons
 *     $null = new \SqlSemantics\Statement\Expression\NullConstant();
 *     $branches = new \SqlSemantics\Statement\Expression\Conditional\SqliteCaseBranches(null, new \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout(), new \SqlSemantics\Statement\Expression\Conditional\SqliteCaseArm($null, $null));
 *     (new \SqlSemantics\Statement\Expression\Conditional\SqliteSearchedCase($branches))->toString() // => 'CASE WHEN NULL THEN NULL END'
 */
final class SqliteSearchedCase implements ScalarExpression
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains the ordered truth tests and their lazy result alternatives.
     */
    public function __construct(public readonly SqliteCaseBranches $branches, public readonly \SqlSemantics\Statement\Expression\Rendering\SqliteCaseLayout $layout = new \SqlSemantics\Statement\Expression\Rendering\SqliteCaseLayout())
    {
    }

    /**
     * Result alternatives describe runtime selection, not missing implementation.
     */
    public function type(): SqliteChoiceDomain|NullDomain|Invalid
    {
        return $this->branches->type();
    }

    /**
     * NULL tests are untrue; only selected result branches determine possible NULL outputs.
     */
    public function nullability(): Nullability
    {
        return $this->branches->nullability();
    }

    /**
     * Resolution visits every branch although runtime evaluation short-circuits.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return $this->branches->references();
    }

    /**
     * Keeps short-circuit behavior and branch order in reconstructed SQL.
     */
    public function toString(): string
    {
        return $this->layout->finish($this->branches->arms[0]->layout->append($this->layout->start(null), $this->branches->toString()));
    }
}
