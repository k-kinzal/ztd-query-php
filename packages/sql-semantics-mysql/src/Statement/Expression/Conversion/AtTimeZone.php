<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;

/**
 * A TIMESTAMP value converted to a DATETIME at a time zone: `CAST(expr AT TIME ZONE [INTERVAL] 'zone' AS DATETIME[(p)])` (MySQL 8.0.22 and later).
 *
 * The server accepts UTC conversions. The INTERVAL keyword and the zone name
 * remain distinct semantic operands; named zones depend on the zone catalog.
 * A literal input is TIMESTAMP or NULL. Casts, arithmetic, interval arithmetic
 * and clocks cannot produce a TIMESTAMP field. Other inputs retain their
 * dependencies for resolution against the current schema.
 *
 * Rule: MYSQL-AT-TIME-ZONE-001. Facts: a DATETIME of the given precision;
 * Nullable, including for a non-null operand, because conversion can fail. Precision is between zero and six; input lowering
 * reports a larger precision before publishing a scalar. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
 * Status: Implemented.
 *
 * @visibility public
 * @example Refusing a literal without a TIMESTAMP input role
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a fixed numeric result even when its value is NULL
 *     $source = new \SqlSemantics\Platform\MySql\Statement\Call\Extract(\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral());
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone($source, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a numeric aggregate with unresolved input dependencies
 *     $source = new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Average, [new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('t'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone($source, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Reading the zone
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE CAST(a AT TIME ZONE 'UTC' AS DATETIME(6))");
 *     [$query->statement->query->where->zone->value, $query->statement->query->where->precision] // => ['UTC', '6']
 * @example Refusing an impossible fractional precision
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00'), precision: '7') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class AtTimeZone implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The converted TIMESTAMP value
     * @param Text $zone The time zone
     * @param bool $interval Whether INTERVAL is written before the zone
     * @param string|null $precision The fractional seconds precision of the DATETIME exactly as written
     */
    public function __construct(public readonly Scalar $operand, public readonly Text $zone, public readonly bool $interval = false, public readonly ?string $precision = null)
    {
        Check::input($zone->radix === null, 'A time zone is a quoted string.');
        Check::input($precision === null || preg_match('/\A[0-9]+\z/', $precision) === 1, 'A precision is an unsigned integer.');
        Check::input((int) ($precision ?? '0') <= 6, 'A DATETIME fractional precision is at most six.');
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
        } elseif ($domain !== null && $this->zone->value !== 'UTC' && $this->zone->value !== '+00:00') {
            $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion($this, false));
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
        $out->node($this->zone)->keyword('AS', 'DATETIME');
        if ($this->precision !== null) {
            $out->glue()->symbol('(')->spelled($this->precision)->symbol(')');
        }
        $out->symbol(')');
    }
}
