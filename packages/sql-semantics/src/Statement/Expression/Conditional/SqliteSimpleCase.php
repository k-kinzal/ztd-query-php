<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Conditional;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;
use SqlSemantics\Statement\Type\Unresolved;

/**
 * A lazy equality choice whose base is evaluated exactly once and supplies comparison affinity.
 * @visibility public
 * @example A NULL base selects the fallback even when a WHEN test is also NULL
 *     $null = new \SqlSemantics\Statement\Expression\NullConstant();
 *     $branches = new \SqlSemantics\Statement\Expression\Conditional\SqliteCaseBranches(null, new \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout(), new \SqlSemantics\Statement\Expression\Conditional\SqliteCaseArm($null, $null));
 *     (new \SqlSemantics\Statement\Expression\Conditional\SqliteSimpleCase($null, $branches))->type() // => \SqlSemantics\Statement\Type\NullDomain::Null
 */
final class SqliteSimpleCase implements ScalarExpression
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The base remains one semantic operand rather than being copied into every test.
     */
    public function __construct(public readonly ScalarExpression $base, public readonly SqliteCaseBranches $branches, public readonly \SqlSemantics\Statement\Expression\Rendering\SqliteCaseLayout $layout = new \SqlSemantics\Statement\Expression\Rendering\SqliteCaseLayout())
    {
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($this), 'A simple CASE retains only immutable semantic operands.');
    }

    /**
     * A known NULL base always chooses ELSE, after all operands have resolved successfully.
     */
    public function type(): TypeDescriptor|NullDomain|Unresolved|Invalid|SqliteNumericDomain|SqliteChoiceDomain
    {
        $base = $this->base->type();
        $invalid = $this->branches->invalid();
        if ($base instanceof Invalid || $invalid !== null) {
            return $base instanceof Invalid ? $base : $invalid;
        }
        return $this->base->nullability() === Nullability::AlwaysNull
            ? ($this->branches->otherwise ?? new NullConstant())->type()
            : $this->branches->type();
    }

    /**
     * Equality never matches a NULL base; other bases keep conservative branch facts.
     */
    public function nullability(): Nullability
    {
        if ($this->base->type() instanceof Invalid || $this->branches->invalid() !== null) {
            return Nullability::Unknown;
        }
        return $this->base->nullability() === Nullability::AlwaysNull
            ? ($this->branches->otherwise ?? new NullConstant())->nullability()
            : $this->branches->nullability();
    }

    /**
     * Keeps the single base dependency followed by the actual test and result dependencies.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [...$this->base->references(), ...$this->branches->references()];
    }

    /**
     * Preserves single base evaluation and SQLite's equality comparison rules.
     */
    public function toString(): string
    {
        return $this->layout->finish($this->branches->arms[0]->layout->append($this->layout->start($this->base->toString()), $this->branches->toString()));
    }
}
