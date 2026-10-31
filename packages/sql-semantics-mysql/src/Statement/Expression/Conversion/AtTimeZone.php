<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;

/**
 * A TIMESTAMP value converted to a DATETIME at a time zone: `CAST(expr AT TIME ZONE [INTERVAL] 'zone' AS DATETIME[(p)])` (MySQL 8.0.22 and later).
 *
 * The server accepts UTC conversions. The INTERVAL keyword and the zone name
 * remain distinct semantic operands. Only 'UTC' and '+00:00' are admitted;
 * the named UTC zone also depends on the zone catalog.
 * A literal input is TIMESTAMP or NULL. Casts, arithmetic, interval arithmetic
 * and clocks cannot produce a TIMESTAMP field. Other inputs retain their
 * dependencies for resolution against the current schema.
 *
 * Rule: MYSQL-AT-TIME-ZONE-001. Facts: a DATETIME of the given precision;
 * Nullable, including for a non-null operand, because conversion can fail. A precision above six cannot be
 * constructed: the server refuses it while parsing the cast (ER_TOO_BIG_PRECISION), also inside stored
 * program bodies. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
 * Status: Implemented.
 *
 * @visibility public
 * @example Refusing a literal without a TIMESTAMP input role
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone::ZeroOffset) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a fixed numeric result even when its value is NULL
 *     $source = new \SqlSemantics\Platform\MySql\Statement\Call\Extract(\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral());
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone($source, \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone::ZeroOffset) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a numeric aggregate with unresolved input dependencies
 *     $source = new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Average, [new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('t'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone($source, \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone::ZeroOffset) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a precision above six
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone::ZeroOffset, false, '7') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Reading the zone
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE CAST(a AT TIME ZONE 'UTC' AS DATETIME(6))");
 *     [$query->query->where->zone->value, $query->query->where->precision] // => ['UTC', '6']
 * @example Refusing a fixed predicate result even when its operands are unresolved
 *     $operand = new \SqlSemantics\Platform\MySql\Statement\Expression\Comparison(\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter());
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone($operand, \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone::ZeroOffset) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class AtTimeZone implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar $operand The converted TIMESTAMP value
     * @param TimeZone $zone The named UTC zone or zero offset
     * @param bool $interval Whether INTERVAL is written before the zone
     * @param string|null $precision The fractional seconds precision of the DATETIME exactly as written, at most 6
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the precision exceeds 6
     */
    public function __construct(public readonly Scalar $operand, public readonly TimeZone $zone, public readonly bool $interval = false, public readonly ?string $precision = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input($precision === null || preg_match('/\A[0-9]+\z/', $precision) === 1, 'A precision is an unsigned integer.');
        Check::input($precision === null || (int) $precision <= 6, 'A DATETIME precision is at most 6.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\TimeZoneOperands())->input($operand) !== false, 'An AT TIME ZONE operand must admit a TIMESTAMP or NULL input role.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the operand; the result is a DATETIME.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = (new Operands())->single($derivation->scalar($this->operand, $environment), $derivation);
        $domain = (new \SqlSemantics\Platform\MySql\Rules\Typing\Precision())->domain($fact->type);
        if ($domain !== null && $domain->field !== Field::Timestamp && $domain->kind !== Kind::Null && (new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\TimeZoneOperands())->literal($this->operand) !== true) {
            $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion($this, true));
        }
        $decimals = (int) ($this->precision ?? '0');

        return new ScalarFact(new Known(new Domain(Kind::DateTime, Field::DateTime, 19 + ($decimals > 0 ? $decimals + 1 : 0), $decimals, false, null, [], Coercibility::Numeric)), \SqlSemantics\Statement\Type\Nullability::Nullable);
    }

    /**
     * Writes the cast at the time zone.
     */
    public function render(Output $out): void
    {
        $out->keyword('CAST')->glue()->symbol('(')->node($this->operand)->keyword('AT', 'TIME', 'ZONE');
        if ($this->interval) {
            $out->keyword('INTERVAL');
        }
        $out->spelled($out->quote($this->zone->value))->keyword('AS', 'DATETIME');
        if ($this->precision !== null) {
            $out->glue()->symbol('(')->spelled($this->precision)->symbol(')');
        }
        $out->symbol(')');
    }
}
