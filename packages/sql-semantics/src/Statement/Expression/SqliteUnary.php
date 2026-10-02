<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;
use SqlSemantics\Statement\Type\Unresolved;

/**
 * A unary computation retaining its operand and declaration dependencies.
 * @visibility public
 * @example Keeping NULL propagation explicit
 *     (new \SqlSemantics\Statement\Expression\SqliteUnary(\SqlSemantics\Statement\Expression\SqliteUnaryOperator::Not, new \SqlSemantics\Statement\Expression\NullConstant()))->type() // => \SqlSemantics\Statement\Type\NullDomain::Null
 */
final class SqliteUnary implements ScalarExpression
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The operation's facts are derived from its immutable semantic operand.
     */
    public function __construct(public readonly SqliteUnaryOperator $operator, public readonly ScalarExpression $operand)
    {
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($operand), 'An operand must contain only immutable semantic values.');
    }

    /**
     * Negation may promote an integer; plus retains the value without restoring column affinity.
     */
    public function type(): TypeDescriptor|NullDomain|Unresolved|Invalid|SqliteNumericDomain|SqliteChoiceDomain
    {
        $input = $this->operand->type();
        if ($input instanceof Invalid || $input instanceof NullDomain || $this->operator === SqliteUnaryOperator::Plus) {
            return $input;
        }
        if ($this->operator !== SqliteUnaryOperator::Negate) {
            return new TypeDescriptor(Builtin::Integer);
        }
        $operand = $this->operand;
        while ($operand instanceof Rendering\GroupedExpression) {
            $operand = $operand->operand;
        }
        if ($operand instanceof SqliteInteger && !$operand->negative) {
            return (new SqliteInteger($operand->integer, true, $operand->uppercasePrefix))->type();
        }
        if ($operand instanceof SqliteReal) {
            return $input;
        }
        return SqliteNumericDomain::IntegerOrReal;
    }

    /**
     * Every prefix operation propagates a NULL input.
     */
    public function nullability(): Nullability
    {
        return $this->type() instanceof Invalid ? Nullability::Unknown : $this->operand->nullability();
    }

    /**
     * Retains the actual lookup objects rather than reconstructing names independently.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return $this->operand->references();
    }

    /**
     * Groups the operand so nested negation cannot become a line comment or change precedence.
     */
    public function toString(): string
    {
        return $this->operator->value . ' (' . $this->operand->toString() . ')';
    }
}
