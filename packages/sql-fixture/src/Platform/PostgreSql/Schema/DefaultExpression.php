<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Analysis\NumericLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DatetimeDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\FloatDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;
use SqlSemantics\Statement\Scalar;

/**
 * Evaluates a DEFAULT expression into the PHP value it denotes.
 *
 * A constant, a signed number and a constant cast to a type that keeps it have
 * a value; any other expression, such as a function call or a cast that
 * truncates or converts, is computed by the server when a row is inserted and
 * has none.
 *
 * @visibility root
 */
final class DefaultExpression
{
    /**
     * The type keywords a cast of an integer keeps the value of.
     */
    public const INTEGER_TARGETS = [TypeKeyword::Int, TypeKeyword::Integer, TypeKeyword::Smallint, TypeKeyword::Bigint, TypeKeyword::Real, TypeKeyword::DoublePrecision];

    /**
     * The type keywords a cast of a fraction keeps the value of.
     */
    public const FRACTION_TARGETS = [TypeKeyword::Real, TypeKeyword::DoublePrecision];

    /**
     * Returns the value of a constant expression, and null for an expression the server computes.
     */
    public function evaluate(Scalar $expression): int|float|bool|string|null
    {
        return match (true) {
            $expression instanceof Constant => $this->constant($expression),
            $expression instanceof Cast => $this->cast($expression),
            $expression instanceof UnaryOperation => $this->signed($expression),
            $expression instanceof BooleanLiteral => $expression->value,
            default => null,
        };
    }

    /**
     * Returns the value of a cast whose target type keeps the value of its operand unchanged, and null otherwise.
     */
    public function cast(Cast $cast): int|float|bool|string|null
    {
        $value = $this->evaluate($cast->operand);
        $target = $cast->type->designation;
        if ($value === null || $cast->type->array !== null) {
            return null;
        }
        $kept = match (true) {
            is_string($value) => $this->keepsText($target),
            is_bool($value) => $target instanceof KeywordDesignation && $target->keyword === TypeKeyword::Boolean,
            default => $this->keepsNumber($target, $value),
        };

        return $kept ? $value : null;
    }

    /**
     * Tells whether a cast to the type keeps a string as it is: the type takes no length, precision or other modifier that would cut it.
     */
    public function keepsText(TypeDesignation $target): bool
    {
        return match (true) {
            $target instanceof NamedDesignation => $target->modifiers === [],
            $target instanceof CharacterDesignation => $target->builtin() === Builtin::Varchar && $target->length === null,
            $target instanceof DatetimeDesignation => $target->precision === null,
            $target instanceof KeywordDesignation => $target->keyword === TypeKeyword::Json,
            default => false,
        };
    }

    /**
     * Tells whether a cast to the type keeps a number as it is: an integer type keeps an integer, and an unconstrained decimal or floating type keeps any number.
     */
    public function keepsNumber(TypeDesignation $target, int|float $value): bool
    {
        return match (true) {
            $target instanceof DecimalDesignation => $target->modifiers === [],
            $target instanceof FloatDesignation => $target->precision === null,
            $target instanceof KeywordDesignation => in_array($target->keyword, is_int($value) ? self::INTEGER_TARGETS : self::FRACTION_TARGETS, true),
            default => false,
        };
    }

    /**
     * Returns the value a constant is written with; a bit string has none.
     */
    public function constant(Constant $constant): int|float|string|null
    {
        $value = $constant->value;

        return match (true) {
            $value instanceof StringConstant => $value->value,
            $value instanceof IntegerConstant => (new NumericLiteral())->decode($value->digits),
            $value instanceof NumericConstant => (new NumericLiteral())->decode($value->text),
            default => null,
        };
    }

    /**
     * Returns a number with a written sign, and null for any other unary operation.
     */
    public function signed(UnaryOperation $operation): int|float|null
    {
        $sign = $operation->operator->qualifiers === [] ? $operation->operator->name->value : null;
        $number = $this->evaluate($operation->operand);
        if (!in_array($sign, ['-', '+'], true) || !(is_int($number) || is_float($number))) {
            return null;
        }

        return $sign === '-' ? -$number : $number;
    }

    /**
     * Tells whether the expression draws the next value of a sequence.
     */
    public function isSequenceCall(Scalar $expression): bool
    {
        return $expression instanceof FunctionCall && $expression->name->last()->value === 'nextval';
    }
}
