<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\BooleanInput;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCastLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedTextLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCaseLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctnessLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRangeLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Membership\IntegerMembershipLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTestLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Collation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression as ParsedCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Between as ParsedRange;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\DistinctTest as ParsedDistinctness;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTest as ParsedTruthTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest as ParsedNullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\InList as ParsedMembership;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral as ParsedBoolean;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

/**
 * Lowers resolved int4 projection operands.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ExpressionLowering
{
    /**
     * Converts a resolved operand, including the exact builtin operator identity.
     * @param bool $simpleOutput Whether a simple statement output resolves a bare string as text
     *
     * @throws InvalidConstruction When the operand has no representation in this domain
     */
    public function lower(
        Scalar $expression,
        SourceBinding|JoinBinding $binding,
        bool $catalogFirst,
        bool $simpleOutput = false,
    ): Arithmetic|BuiltinCast|CollatedText|ColumnReference|IntegerCase|IntegerLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|TruthTest|BooleanLiteral|NullLiteral|NullTest|TextComparison|TextLiteral|WideIntegerLiteral {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof BinaryOperation) {
            return IntegerOperator::tryFrom($expression->operator->name->value) !== null
                ? (new PredicateLowering())->comparison($expression, $binding, $catalogFirst)
                : $this->binary($expression, $binding, $catalogFirst);
        }
        $integer = $this->integerLiteral($expression, $catalogFirst);
        if ($integer !== null) {
            return $integer;
        }

        return match (true) {
            $expression instanceof Cast => (new BuiltinCastLowering())->lower($expression, $binding, $catalogFirst),
            $expression instanceof Collation => (new CollatedTextLowering())->lower($expression, $binding, $catalogFirst),
            $expression instanceof ParsedCase => (new IntegerCaseLowering())->lower($expression, $binding, $catalogFirst),
            $expression instanceof ParsedDistinctness => (new IntegerDistinctnessLowering())->lower($expression, $binding, $catalogFirst),
            $expression instanceof ParsedMembership => (new IntegerMembershipLowering())->lower($expression, $binding, $catalogFirst),
            $expression instanceof ParsedNullTest => (new PredicateLowering())->nullTest($expression, $binding, $catalogFirst),
            $expression instanceof ParsedTruthTest => (new TruthTestLowering())->lower($expression, $binding, $catalogFirst),
            $expression instanceof ParsedRange => (new IntegerRangeLowering())->lower($expression, $binding, $catalogFirst),
            $expression instanceof Constant && $expression->value instanceof StringConstant && $simpleOutput => new TextLiteral($expression->value->value),
            $expression instanceof ParsedBoolean => new BooleanLiteral($expression->value),
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof ParsedColumn => $binding->reference($expression),
            default => throw new InvalidConstruction('The operand has no complete PostgreSQL projection representation.'),
        };
    }

    /**
     * Resolves unknown literals at the selected result or assignment boundary.
     *
     * @throws InvalidConstruction When the selected native input is invalid
     */
    public function result(
        Scalar $operand,
        SourceBinding|JoinBinding $binding,
        bool $catalogFirst,
        ?ColumnKind $destination,
        bool $distinct = false,
        bool $simpleOutput = false,
    ): Arithmetic|BuiltinCast|CollatedText|ColumnReference|IntegerCase|IntegerLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|TruthTest|BooleanLiteral|NullLiteral|NullTest|TextComparison|TextLiteral|WideIntegerLiteral {
        $bareText = $simpleOutput || $destination === ColumnKind::Text;
        if ($destination === ColumnKind::Boolean) {
            $bareText = $distinct;
            if (!$distinct) {
                $boolean = (new BooleanInput())->untyped($operand);
                if ($boolean !== null) {
                    return $boolean;
                }
            }
        }

        return $this->lower($operand, $binding, $catalogFirst, $bareText);
    }

    /**
     * Decodes canonical integer constants and the native folded sign.
     */
    public function integerLiteral(Scalar $expression, bool $catalogFirst): IntegerLiteral|WideIntegerLiteral|null
    {
        $negative = $expression instanceof UnaryOperation && !$expression->operator->explicit
            && $expression->operator->name->value === '-';
        if ($negative) {
            $expression = $expression->operand;
            while ($expression instanceof Grouped) {
                $expression = $expression->operand;
            }
        }
        if (!$expression instanceof Constant) {
            return null;
        }
        $number = $expression->value;
        if ($number instanceof IntegerConstant && (!$negative || $catalogFirst)) {
            return new IntegerLiteral($negative && $number->digits !== '0' ? '-' . $number->digits : $number->digits);
        }
        if (!$number instanceof NumericConstant) {
            return null;
        }

        $digits = $number->integerDigits();
        $maximum = $negative ? '9223372036854775808' : '9223372036854775807';
        if ($digits === null || strlen($digits) > 19 || (strlen($digits) === 19 && strcmp($digits, $maximum) > 0)) {
            return null;
        }
        if ($negative && $digits === '2147483648') {
            return new IntegerLiteral('-2147483648');
        }

        return new WideIntegerLiteral($negative ? '-' . $digits : $digits);
    }

    /**
     * Resolves the exact builtin operation and both of its int4 operands.
     * @throws InvalidConstruction When an operator or operand is outside this checked scope
     */
    public function binary(BinaryOperation $expression, SourceBinding|JoinBinding $binding, bool $catalogFirst): Arithmetic
    {
        $qualifiers = array_column($expression->operator->qualifiers, 'value');
        Check::input(
            ($qualifiers === [] && $catalogFirst) || $qualifiers === ['pg_catalog'],
            'Integer arithmetic must resolve to a pg_catalog operator.',
        );
        $operator = ArithmeticOperator::tryFrom($expression->operator->name->value);
        Check::input($operator !== null, 'The operator has no checked PostgreSQL int4 arithmetic representation.');
        $left = $this->lower($expression->left, $binding, $catalogFirst);
        $right = $this->lower($expression->right, $binding, $catalogFirst);
        $typeError = 'Arithmetic operands must have the int4 role.';
        Check::input($left instanceof Arithmetic || $left instanceof ColumnReference || $left instanceof IntegerCase || $left instanceof IntegerLiteral, $typeError);
        Check::input($right instanceof Arithmetic || $right instanceof ColumnReference || $right instanceof IntegerCase || $right instanceof IntegerLiteral, $typeError);

        return new Arithmetic($operator, $left, $right);
    }
}
