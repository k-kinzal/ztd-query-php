<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
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
 * A TIMESTAMP value converted to a DATETIME at a time zone: `CAST(expr AT TIME ZONE [INTERVAL] 'zone' AS DATETIME[(p)])` (`Item_func_at_time_zone`, MySQL 8.0.22 and later).
 *
 * The server accepts the zone `'UTC'`, and `'+00:00'` written with
 * INTERVAL; the INTERVAL keyword is kept because it decides which spelling
 * is accepted.
 *
 * Rule: MYSQL-AT-TIME-ZONE-001. Facts: a DATETIME of the given precision;
 * NULL when the operand is. A precision above 6 is a problem the server
 * finds while it parses the cast. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the zone
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE CAST(a AT TIME ZONE 'UTC' AS DATETIME(6))");
 *     [$query->statement->where->zone->value, $query->statement->where->precision] // => ['UTC', '6']
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
    }

    /**
     * Derives the operand; the result is a DATETIME.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = (new Operands())->single($derivation->scalar($this->operand, $environment), $derivation);
        if ((int) ($this->precision ?? '0') > 6) {
            $problem = new TooBigPrecision((int) $this->precision, 'CAST');
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true));
        }
        $decimals = min(6, (int) ($this->precision ?? '0'));

        return new ScalarFact(new Known(new Domain(Kind::DateTime, Field::DateTime, 19 + ($decimals > 0 ? $decimals + 1 : 0), $decimals, false, null, [], Coercibility::Numeric)), $fact->nullability);
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
