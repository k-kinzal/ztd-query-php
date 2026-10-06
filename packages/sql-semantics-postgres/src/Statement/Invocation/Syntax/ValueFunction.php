<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A function written as a bare keyword, such as CURRENT_DATE or CURRENT_TIMESTAMP(3).
 *
 * Mirrors PostgreSQL's `SQLValueFunction` node (and the call SYSTEM_USER
 * becomes). Rule: PG-VALUE-FUNCTION-001. Facts: the documented type, with
 * the precision as type modifier, reduced to 6 as the server does; NOT NULL
 * except CURRENT_SCHEMA and SYSTEM_USER. The result column is named after the
 * keyword in lower case. Termination: constant work.
 * Source: https://www.postgresql.org/docs/17/functions-datetime.html#FUNCTIONS-DATETIME-CURRENT,
 * https://www.postgresql.org/docs/17/functions-info.html#FUNCTIONS-INFO-SESSION. Status: Implemented.
 *
 * @visibility public
 * @example Reading a precise current timestamp
 *     $value = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunction(
 *         \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunctionKind::CurrentTimestamp,
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('3'),
 *     );
 *     [$value->precision->digits, $value->outputName()->value] // => ['3', 'current_timestamp']
 * @example Rejecting a precision on CURRENT_DATE
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunction(
 *         \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunctionKind::CurrentDate,
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('3'),
 *     ) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ValueFunction implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * The largest fractional-seconds precision of the time types.
     */
    public const MAXIMUM_PRECISION = 6;

    /**
     * @param ValueFunctionKind $kind The function
     * @param IntegerConstant|null $precision The fractional-seconds precision written in parentheses
     */
    public function __construct(public readonly ValueFunctionKind $kind, public readonly ?IntegerConstant $precision = null)
    {
        Check::input($precision === null || $kind->precise(), 'Only the time functions take a precision.');
    }

    /**
     * Names an unaliased result column after the keyword.
     */
    public function outputName(): Name
    {
        return new Name(strtolower($this->kind->value));
    }

    /**
     * Derives the documented type and NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $builtin = $this->kind->builtin();
        $nullability = $this->kind->nullable() ? Nullability::Nullable : Nullability::NotNull;
        if ($this->precision === null) {
            return new ScalarFact(new Known($builtin), $nullability);
        }
        $digits = $this->precision->digits;
        $precision = (new Numerals())->within($digits, (string) self::MAXIMUM_PRECISION) ? (int) $digits : self::MAXIMUM_PRECISION;

        return new ScalarFact(new Known(new Parameterized($builtin, $precision)), $nullability);
    }

    /**
     * Writes the keyword and the precision.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value);
        if ($this->precision !== null) {
            $out->glue()->symbol('(')->node($this->precision)->symbol(')');
        }
    }
}
