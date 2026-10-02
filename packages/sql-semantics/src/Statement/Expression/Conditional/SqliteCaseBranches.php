<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Conditional;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;

/**
 * Ordered CASE alternatives, their declaration dependencies, and their possible result domains.
 * @visibility public
 * @example An omitted ELSE contributes a NULL result
 *     $null = new \SqlSemantics\Statement\Expression\NullConstant();
 *     $branches = new \SqlSemantics\Statement\Expression\Conditional\SqliteCaseBranches(null, new \SqlSemantics\Statement\Expression\Conditional\SqliteCaseArm($null, $null));
 *     $branches->nullability() // => \SqlSemantics\Statement\Declaration\Nullability::AlwaysNull
 */
final class SqliteCaseBranches
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var non-empty-list<SqliteCaseArm>
     */
    public readonly array $arms;

    /**
     * A selection needs at least one test; PHP null means ELSE is omitted.
     */
    public function __construct(public readonly ?ScalarExpression $otherwise, SqliteCaseArm ...$arms)
    {
        \SqlSemantics\Statement\Validation\Check::input($arms !== [], 'A CASE expression needs at least one branch.');
        $this->arms = array_values($arms);
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($this), 'CASE alternatives retain only immutable semantic operands.');
    }

    /**
     * Every operand must resolve even when short-circuit evaluation would not visit it.
     */
    public function invalid(): ?Invalid
    {
        foreach ($this->operands() as $operand) {
            $type = $operand->type();
            if ($type instanceof Invalid) {
                return $type;
            }
        }
        return null;
    }

    /**
     * SQLite returns the selected value without coercing other branches to a common type.
     */
    public function type(): SqliteChoiceDomain|NullDomain|Invalid
    {
        $invalid = $this->invalid();
        if ($invalid !== null) {
            return $invalid;
        }
        $types = [];
        foreach ($this->results() as $result) {
            $type = $result->type();
            \SqlSemantics\Statement\Validation\Check::input(!$type instanceof Invalid, 'Invalid operands have already been diagnosed.');
            $types[] = $type;
        }
        $domain = new SqliteChoiceDomain(...$types);
        return $domain->alternatives === [NullDomain::Null] ? NullDomain::Null : $domain;
    }

    /**
     * Derives a conservative NULL fact from result branches, independently of test nullability.
     */
    public function nullability(): Nullability
    {
        if ($this->invalid() !== null) {
            return Nullability::Unknown;
        }
        $facts = array_map(static fn (ScalarExpression $result): Nullability => $result->nullability(), $this->results());
        if (count(array_filter($facts, static fn (Nullability $fact): bool => $fact === Nullability::NotNull)) === count($facts)) {
            return Nullability::NotNull;
        }
        if (count(array_filter($facts, static fn (Nullability $fact): bool => $fact === Nullability::AlwaysNull)) === count($facts)) {
            return Nullability::AlwaysNull;
        }
        return in_array(Nullability::Unknown, $facts, true) ? Nullability::Unknown : Nullability::MaybeNull;
    }

    /**
     * Includes every written result and the implicit NULL fallback.
     * @return non-empty-list<ScalarExpression>
     */
    public function results(): array
    {
        return [...array_map(static fn (SqliteCaseArm $arm): ScalarExpression => $arm->result, $this->arms), $this->otherwise ?? new NullConstant()];
    }

    /**
     * Retains evaluation order without expanding a simple CASE's shared base expression.
     * @return non-empty-list<ScalarExpression>
     */
    public function operands(): array
    {
        $operands = [];
        foreach ($this->arms as $arm) {
            $operands[] = $arm->test;
            $operands[] = $arm->result;
        }
        $operands[] = $this->otherwise ?? new NullConstant();
        return $operands;
    }

    /**
     * Keeps exact lookup objects, including lookups in branches that might never execute.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        $references = [];
        foreach ($this->operands() as $operand) {
            array_push($references, ...$operand->references());
        }
        return $references;
    }

    /**
     * Reconstructs alternatives in the order in which their tests are evaluated.
     */
    public function toString(): string
    {
        return implode(' ', array_map(static fn (SqliteCaseArm $arm): string => $arm->toString(), $this->arms)) . ($this->otherwise === null ? '' : ' ELSE ' . $this->otherwise->toString());
    }
}
