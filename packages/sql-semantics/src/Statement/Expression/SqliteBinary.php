<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;

/**
 * A built-in computation with ordered semantic operands and derived result facts.
 * @visibility public
 * @example Distinguishing a NULL-safe comparison from NULL propagation
 *     $null = new \SqlSemantics\Statement\Expression\NullConstant();
 *     (new \SqlSemantics\Statement\Expression\SqliteBinary($null, \SqlSemantics\Statement\Expression\SqliteBinaryOperator::Is, $null))->nullability() // => \SqlSemantics\Statement\Declaration\Nullability::NotNull
 */
final class SqliteBinary implements ScalarExpression
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Result facts cannot be supplied independently of the operation and its operands.
     */
    public function __construct(public readonly ScalarExpression $left, public readonly SqliteBinaryOperator $operator, public readonly ScalarExpression $right, public readonly ?Rendering\SqliteBinaryLayout $layout = null)
    {
        \SqlSemantics\Statement\Validation\Check::input($layout === null || $layout->operator === $operator, 'The lexical operator must match the actual binary operation.');
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($this), 'A computation retains only immutable semantic operands.');
    }

    /**
     * Keeps runtime numeric alternatives explicit and propagates invalid operand references.
     */
    public function type(): TypeDescriptor|NullDomain|Invalid|SqliteNumericDomain
    {
        if ($this->discardsOperands()) {
            return new TypeDescriptor(Builtin::Integer);
        }
        $left = $this->left->type();
        $right = $this->right->type();
        if ($left instanceof Invalid || $right instanceof Invalid) {
            return $left instanceof Invalid ? $left : $right;
        }
        if ($this->nullability() === Nullability::AlwaysNull) {
            return NullDomain::Null;
        }
        if ($this->operator->arithmetic()) {
            return SqliteNumericDomain::IntegerOrReal;
        }
        return new TypeDescriptor($this->operator === SqliteBinaryOperator::Concatenate ? Builtin::Text : Builtin::Integer);
    }

    /**
     * Distinguishes NULL propagation, truth logic, and arithmetic that can produce NULL.
     */
    public function nullability(): Nullability
    {
        if ($this->discardsOperands()) {
            return Nullability::NotNull;
        }
        if ($this->left->type() instanceof Invalid || $this->right->type() instanceof Invalid) {
            return Nullability::Unknown;
        }
        if ($this->operator->neverNull()) {
            return Nullability::NotNull;
        }
        $left = $this->left->nullability();
        $right = $this->right->nullability();
        if ($left === Nullability::AlwaysNull && $right === Nullability::AlwaysNull) {
            return Nullability::AlwaysNull;
        }
        if (!$this->operator->logical() && ($left === Nullability::AlwaysNull || $right === Nullability::AlwaysNull)) {
            return Nullability::AlwaysNull;
        }
        if ($this->operator->arithmetic()) {
            return Nullability::MaybeNull;
        }
        return $left === Nullability::NotNull && $right === Nullability::NotNull ? Nullability::NotNull : Nullability::MaybeNull;
    }

    /**
     * Exposes the operand lookups with original scope and declaration identities intact.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return $this->discardsOperands() ? [] : [...$this->left->references(), ...$this->right->references()];
    }

    /**
     * SQLite reduces integer-zero conjunctions before name lookup, but not floating or quoted zero.
     * Grouped numerals use the same rule; separated QNUMBER digits are decoded at a later phase.
     */
    public function discardsOperands(): bool
    {
        if ($this->operator !== SqliteBinaryOperator::And) {
            return false;
        }
        foreach ([$this->left, $this->right] as $operand) {
            while ($operand instanceof Rendering\GroupedExpression) {
                $operand = $operand->operand;
            }
            if ($operand instanceof SqliteInteger && !$operand->negative && !str_contains($operand->integer->digits, '_') && $operand->value->value === '0') {
                return true;
            }
            if ($operand instanceof SqliteInList && $operand->choices === [] && !$operand->negated) {
                return true;
            }
            if ($operand instanceof self && $operand->discardsOperands()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Preserves evaluation grouping without depending on parser productions or raw SQL.
     */
    public function toString(): string
    {
        if ($this->layout !== null && !$this->layout->groupOperands) {
            $precedence = new Rendering\SqlitePrecedence();
            $left = $this->left->toString();
            $right = $this->right->toString();
            return $this->layout->between($precedence->grouped($this->left, $this->operator, false) ? '(' . $left . ')' : $left, $precedence->grouped($this->right, $this->operator, true) ? '(' . $right . ')' : $right);
        }
        return '(' . $this->left->toString() . ')' . ($this->layout?->symbol() ?? ' ' . $this->operator->value . ' ') . '(' . $this->right->toString() . ')';
    }
}
