<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Rules\Expression\NumericResult;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Typing\Numbers;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A binary arithmetic or bit operation of the bit_expr level, in the order written (`Item_func_plus`, `Item_func_int_div`, `Item_func_bit_or`, …).
 *
 * The operators associate to the left with the levels of
 * MYSQL-PRECEDENCE-001; an operand that would be read differently without a
 * grouping is rejected, among them a right operand of `+` or `-` that
 * starts with a leading interval `INTERVAL n unit + x`, which the parser
 * reads as a trailing interval.
 *
 * Rule: MYSQL-ARITHMETIC-001. Facts: the type follows
 * MYSQL-NUMERIC-RESULT-001. `+`, `-`, `*` and the bit operators are NULL
 * when an operand is; `/`, DIV and `%` are also NULL for a zero divisor.
 * Both operands take a single value. Terminates: the operands are strict parts.
 * A division retains its resolved precision increment for execution. On modern
 * releases an initially untyped division can receive integer operands from an
 * enclosing expression; it then evaluates as an integer while reporting the
 * division's fractional metadata. This is the delayed propagation path in
 * MySQL 8.4.7 Item_func::propagate_type and Item_func_div::resolve_type.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-functions.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Writing MOD as the remainder operator
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a MOD 2 = 1')->toString() // => 'SELECT a FROM t WHERE a MOD 2 = 1'
 * @example Typing a division of integers as DECIMAL
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE 1 / 2');
 *     $query->query->where->scalarInformation()->type->descriptor->name() // => 'DECIMAL'
 */
final class Arithmetic implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    private ?int $precisionIncrement = null;

    /**
     * @param int|null $divisionPrecision The resolved division increment, required with a bound division
     * @param ArithmeticOperator $operator The operator
     * @param Scalar $left The left operand
     * @param Scalar $right The right operand
     * @param bool $modWord Whether modulo is written MOD instead of %
     */
    public function __construct(public readonly ArithmeticOperator $operator, public readonly Scalar $left, public readonly Scalar $right, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $modWord = false, ?int $divisionPrecision = null)
    {
        if ($divisionPrecision !== null) {
            $this->initializeDivisionPrecision($divisionPrecision);
        }
        if ($scalarInformation !== null) {
            Check::input($operator !== ArithmeticOperator::Divide || $divisionPrecision !== null, 'A bound division supplies its calculation precision.');
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input(!$modWord || $operator === ArithmeticOperator::Modulo, 'MOD names the modulo operation.');
        $precedence = new Precedence();
        Check::input($precedence->fits($left, $operator->level(), $operator->level()), 'The left operand of ' . $operator->value . ' needs a grouping to keep its place.');
        Check::input($precedence->opening($right) > $operator->level(), 'The right operand of ' . $operator->value . ' needs a grouping to keep its place.');
        Check::input(!$precedence->leading($right) instanceof IntervalAddition || ($operator !== ArithmeticOperator::Plus && $operator !== ArithmeticOperator::Minus), 'A leading interval after ' . $operator->value . ' is read as a trailing interval and needs a grouping.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives both operands and the type of the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $left = $operands->single($derivation->scalar($this->left, $environment), $derivation);
        $right = $operands->single($derivation->scalar($this->right, $environment), $derivation);
        (new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\SpatialOperands())->check($derivation, $this->operator, [$left->type, $right->type]);
        $type = (new NumericResult())->binary($this->operator, $this->left, $left, $this->right, $right, $derivation->context->profile->grammar);
        if ($this->operator->bitwise() && (new NumericResult())->binaryOperation($this->operator, $this->left, $left, $this->right, $right)) {
            Deprecation::raise(Deprecated::BinaryBitwise, $derivation);
        }
        $domains = (new Precision())->all([$left->type, $right->type]);
        $settings = Settings::of($derivation->context);
        if ($this->operator === ArithmeticOperator::Divide) {
            $this->initializeDivisionPrecision($settings->divPrecisionIncrement);
        }
        if ($domains !== null && ($type instanceof Known || !$this->operator->bitwise())) {
            $numbers = new Numbers($settings->divPrecisionIncrement, $settings->unsignedSubtraction, in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true), $derivation->context->profile->grammar === GrammarRelease::MySql8044);
            $bytes = $domains;
            $domains = [$numbers->numeric($this->left, $domains[0]), $numbers->numeric($this->right, $domains[1])];
            if (in_array($this->operator, [ArithmeticOperator::Plus, ArithmeticOperator::Minus, ArithmeticOperator::Multiply], true) && in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                $facts = $derivation->facts();
                $domains = [$numbers->signed($this->left, $domains[0]), $numbers->signed($this->right, $domains[1])];
            }
            $type = new Known($this->operator->bitwise() && $type instanceof Known ? (TypeClass::of($type->descriptor) === TypeClass::Unsigned ? $numbers->bits() : $numbers->binaryBits($this->operator, $bytes[0], $bytes[1])) : $numbers->binary($this->operator, $domains[0], $domains[1]));
        }
        $type = $this->preparedType($type, $domains, $derivation->context->profile->grammar);
        $divides = $this->operator === ArithmeticOperator::Divide || $this->operator === ArithmeticOperator::IntegerDivide || $this->operator === ArithmeticOperator::Modulo;

        return new ScalarFact($type, $divides ? Nullability::Nullable : $left->nullability->propagate($right->nullability));
    }

    /**
     * Retains integer evaluation when enclosing context resolves an initially untyped division.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain>|null $domains Numeric operand domains
     */
    public function preparedType(\SqlSemantics\Statement\Type\TypeFact $type, ?array $domains, GrammarRelease $release): \SqlSemantics\Statement\Type\TypeFact
    {
        if ($this->operator === ArithmeticOperator::Divide && $type instanceof Known && $type->descriptor instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain && $type->descriptor->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Decimal && $domains !== null && !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) && \SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterInference::pending($this->left) && \SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterInference::pending($this->right) && $domains[0]->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer && $domains[1]->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer) {
            $domain = $type->descriptor;
            $type = new Known(new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong, $domain->length, 0, $domains[0]->unsigned || $domains[1]->unsigned, displayDecimals: $domain->decimals));
        }
        return $type;
    }

    /**
     * Completes the division's calculation precision during semantic construction.
     */
    public function initializeDivisionPrecision(int $increment): void
    {
        Check::input($this->operator === ArithmeticOperator::Divide && $increment >= 0 && $increment <= 30, 'Division precision is between zero and thirty.');
        Check::input($this->precisionIncrement === null || $this->precisionIncrement === $increment, 'Division precision is resolved once.');
        $this->precisionIncrement = $increment;
    }

    /**
     * Returns the precision increment owned by this division occurrence.
     */
    public function divisionPrecision(): int
    {
        Check::input($this->precisionIncrement !== null, 'A division must have calculation precision before execution.');
        return $this->precisionIncrement;
    }

    /**
     * Writes the operands around the operator.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        if ($this->modWord) {
            $out->keyword('MOD');
        } elseif ($this->operator === ArithmeticOperator::IntegerDivide) {
            $out->keyword('DIV');
        } else {
            $out->symbol($this->operator->value);
        }
        $out->node($this->right);
    }
}
