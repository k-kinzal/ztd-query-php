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
 * A range test evaluating its subject once, with independently resolved bounds.
 * @visibility public
 * @example A NULL subject cannot satisfy a range
 *     $null = new \SqlSemantics\Statement\Expression\NullConstant();
 *     (new \SqlSemantics\Statement\Expression\SqliteBetween($null, $null, $null))->nullability() // => \SqlSemantics\Statement\Declaration\Nullability::AlwaysNull
 */
final class SqliteBetween implements ScalarExpression
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The subject is a single operand, not duplicated into independently evaluated comparisons.
     */
    public function __construct(public readonly ScalarExpression $subject, public readonly ScalarExpression $lower, public readonly ScalarExpression $upper, public readonly bool $negated = false)
    {
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($this), 'A range test retains only immutable semantic operands.');
    }

    /**
     * A valid range test produces an integer truth value or NULL.
     */
    public function type(): TypeDescriptor|NullDomain|Invalid
    {
        foreach ([$this->subject, $this->lower, $this->upper] as $operand) {
            $type = $operand->type();
            if ($type instanceof Invalid) {
                return $type;
            }
        }
        return $this->nullability() === Nullability::AlwaysNull ? NullDomain::Null : new TypeDescriptor(Builtin::Integer);
    }

    /**
     * A NULL bound alone does not force NULL: the other comparison can already be false.
     */
    public function nullability(): Nullability
    {
        $facts = [];
        foreach ([$this->subject, $this->lower, $this->upper] as $operand) {
            if ($operand->type() instanceof Invalid) {
                return Nullability::Unknown;
            }
            $facts[] = $operand->nullability();
        }
        if ($facts[0] === Nullability::AlwaysNull || ($facts[1] === Nullability::AlwaysNull && $facts[2] === Nullability::AlwaysNull)) {
            return Nullability::AlwaysNull;
        }
        return $facts === [Nullability::NotNull, Nullability::NotNull, Nullability::NotNull] ? Nullability::NotNull : Nullability::MaybeNull;
    }

    /**
     * Retains lookup identities and the single subject dependency.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [...$this->subject->references(), ...$this->lower->references(), ...$this->upper->references()];
    }

    /**
     * Reconstructs a range operation without duplicating subject evaluation.
     */
    public function toString(): string
    {
        return '(' . $this->subject->toString() . ')' . ($this->negated ? ' NOT' : '') . ' BETWEEN (' . $this->lower->toString() . ') AND (' . $this->upper->toString() . ')';
    }
}
