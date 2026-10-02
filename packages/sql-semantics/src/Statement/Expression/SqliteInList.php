<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;

/**
 * Scalar membership in an ordered expression list, including SQLite's empty list.
 * @visibility public
 * @example Empty membership has a definite answer even for a NULL subject
 *     (new \SqlSemantics\Statement\Expression\SqliteInList(new \SqlSemantics\Statement\Expression\NullConstant()))->nullability() // => \SqlSemantics\Statement\Declaration\Nullability::NotNull
 */
final class SqliteInList implements ScalarExpression
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var list<ScalarExpression>
     */
    public readonly array $choices;

    /**
     * List positions and operand identities are retained by immutable construction.
     */
    public function __construct(public readonly ScalarExpression $subject, public readonly bool $negated = false, ScalarExpression ...$choices)
    {
        $this->choices = array_values($choices);
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($this), 'Membership retains only immutable semantic operands.');
    }

    /**
     * Empty membership is a constant truth value: SQLite does not resolve or evaluate its subject.
     */
    public function type(): TypeDescriptor|NullDomain|Invalid
    {
        if ($this->choices === []) {
            return new TypeDescriptor(Builtin::Integer);
        }
        foreach ([$this->subject, ...$this->choices] as $operand) {
            $type = $operand->type();
            if ($type instanceof Invalid) {
                return $type;
            }
        }
        return $this->nullability() === Nullability::AlwaysNull ? NullDomain::Null : new TypeDescriptor(Builtin::Integer);
    }

    /**
     * NULL does not propagate through the empty-list case.
     */
    public function nullability(): Nullability
    {
        if ($this->choices === []) {
            return Nullability::NotNull;
        }
        $facts = [];
        foreach ([$this->subject, ...$this->choices] as $operand) {
            if ($operand->type() instanceof Invalid) {
                return Nullability::Unknown;
            }
            $facts[] = $operand->nullability();
        }
        if ($facts[0] === Nullability::AlwaysNull || array_unique(array_slice($facts, 1), SORT_REGULAR) === [Nullability::AlwaysNull]) {
            return Nullability::AlwaysNull;
        }
        return array_unique($facts, SORT_REGULAR) === [Nullability::NotNull] ? Nullability::NotNull : Nullability::MaybeNull;
    }

    /**
     * Exposes the lookups in the subject and each list position.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        if ($this->choices === []) {
            return [];
        }
        $references = $this->subject->references();
        foreach ($this->choices as $choice) {
            array_push($references, ...$choice->references());
        }
        return $references;
    }

    /**
     * Keeps membership distinct from a chain of equality tests.
     */
    public function toString(): string
    {
        return '(' . $this->subject->toString() . ')' . ($this->negated ? ' NOT' : '') . ' IN (' . implode(', ', array_map(static fn (ScalarExpression $choice): string => $choice->toString(), $this->choices)) . ')';
    }
}
