<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\OdbcEscape;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Resolves NAME_CONST's literal argument restrictions before execution.
 *
 * Its name accepts literal constants. Its value accepts a literal, optionally negated or
 * collated once. Bare boolean values are accepted in MySQL 5.x and refused in modern releases;
 * MySQL 9.1 also accepts a negated or collated boolean value.
 * A NULL name is reserved syntax; other invalid argument forms have error 1210.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html#function_name-const.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NamedConstant
{
    /**
     * Reports a literal-argument problem before argument resolution; other native-call checks retain priority.
     */
    public function check(FunctionCall $call, Derivation $derivation): ?ScalarFact
    {
        if ($call->schema !== null || count($call->arguments) !== 2 || $call->named() || strcasecmp($call->name->value, 'NAME_CONST') !== 0) {
            return null;
        }
        $name = $this->unwrapped($call->arguments[0]->expression);
        $value = $this->unwrapped($call->arguments[1]->expression);
        $modified = $value instanceof Collated || ($value instanceof Unary && $value->operator === UnaryOperator::Minus);
        if ($modified) {
            $value = $this->unwrapped($value->operand);
        }
        $legacy = in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $validName = $this->literal($name) || $name instanceof BooleanLiteral || $name instanceof TemporalLiteral;
        $validValue = $this->literal($value) || ($value instanceof BooleanLiteral && ($legacy || ($modified && $derivation->context->profile->grammar === GrammarRelease::MySql910)));
        $problem = !$validName || !$validValue ? new InvalidNamedConstant() : ($name instanceof NullLiteral ? new InvalidNamedConstant(true) : null);
        if ($problem === null) {
            return null;
        }
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), Nullability::Dependent);
    }

    /**
     * Tells whether an expression is an ordinary literal accepted in either position.
     */
    public function literal(Scalar $node): bool
    {
        return $node instanceof NumberLiteral || $node instanceof StringLiteral || $node instanceof RadixLiteral || $node instanceof NullLiteral;
    }

    /**
     * Removes wrappers which preserve a literal's identity, including ODBC escapes.
     */
    public function unwrapped(Scalar $node): Scalar
    {
        while ($node instanceof Grouped || ($node instanceof Unary && $node->operator === UnaryOperator::Plus) || $node instanceof OdbcEscape) {
            $node = $node instanceof OdbcEscape ? ($node->literal() ?? $node->operand) : $node->operand;
        }

        return $node;
    }
}
