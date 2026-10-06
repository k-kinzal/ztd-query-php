<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Types prefix operator applications.
 *
 * Rule: PG-PREFIX-TYPING-001. The parser folds `-` before a numeric constant,
 * parenthesized or not, into a negative constant: it is `integer` from
 * -2147483648, `bigint` from -9223372036854775808 and `numeric` beyond, and
 * never NULL. Otherwise the catalog prefix operators keep a number or
 * interval type for `-` and `+`, a number type for `@`, give `double
 * precision` for `|/` and `||/`, keep an integer, bit string or `inet` type
 * for `~` and `tsquery` for `!!`; any other operand or operator depends on
 * a declaration (PG-OPERATOR-TYPING-001). The operators are strict.
 * Termination: constant work.
 * Source: https://www.postgresql.org/docs/17/functions-math.html, https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS-NUMERIC. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class PrefixTyping
{
    /**
     * Types a prefix operator application; `-` before a numeric constant is typed as the negative constant it folds to.
     */
    public function prefix(AnalysisContext $context, OperatorName $operator, ScalarFact $operand, Scalar $node): ScalarFact
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }
        if (!$operator->explicit && $operator->name->value === '-' && $node instanceof Constant && ($node->value instanceof IntegerConstant || $node->value instanceof NumericConstant)) {
            return new ScalarFact(new Known($this->negative($node->value)), Nullability::NotNull);
        }

        return new ScalarFact($this->unary($context, $operator, $operand->type), (new OperandChecks())->nullability([$operand]));
    }

    /**
     * Answers the type of a negative numeric constant: `integer` from -2147483648, `bigint` from -9223372036854775808, `numeric` beyond.
     */
    public function negative(IntegerConstant|NumericConstant $value): Builtin
    {
        $numerals = new Numerals();
        $digits = $value instanceof IntegerConstant ? $value->digits : $numerals->integer($value->text);
        if ($digits === null) {
            return Builtin::Numeric;
        }
        if ($numerals->within($digits, '2147483648')) {
            return Builtin::Int4;
        }

        return $numerals->within($digits, '9223372036854775808') ? Builtin::Int8 : Builtin::Numeric;
    }

    /**
     * Types a prefix operator over the type of its operand.
     */
    public function unary(AnalysisContext $context, OperatorName $operator, TypeFact $operand): TypeFact
    {
        if ($operand instanceof Invalid || $operand instanceof Dependent) {
            return $operand;
        }
        $name = $operator->name->value;
        $type = (new Categories())->builtin($operand);
        if (!(new OperatorTyping())->visible($context, $name, $operator) || $type === null || $type === Builtin::Unknown) {
            return (new OperatorTyping())->undeclared($name);
        }
        $numbers = [...Arithmetic::INTEGERS, Builtin::Numeric, Builtin::Float4, Builtin::Float8];
        $keeps = [
            '-' => [...$numbers, Builtin::Interval], '+' => [...$numbers, Builtin::Interval], '@' => $numbers,
            '~' => [...Arithmetic::INTEGERS, Builtin::Bit, Builtin::Varbit, Builtin::Inet], '!!' => [Builtin::Tsquery],
        ];
        if (in_array($type, $keeps[$name] ?? [], true)) {
            return new Known($type);
        }

        return ($name === '|/' || $name === '||/') && in_array($type, $numbers, true) ? new Known(Builtin::Float8) : (new OperatorTyping())->undeclared($name);
    }
}
