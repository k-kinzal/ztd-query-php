<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

/**
 * A TIMESTAMP value converted to a DATETIME at a time zone: `CAST(expr AT TIME ZONE [INTERVAL] 'zone' AS DATETIME[(p)])` (`Item_func_at_time_zone`, MySQL 8.0.22 and later).
 *
 * The server accepts the zone `'UTC'`, and `'+00:00'` written with
 * INTERVAL; the INTERVAL keyword is kept because it decides which spelling
 * is accepted.
 *
 * Rule: MYSQL-AT-TIME-ZONE-001. Facts: a DATETIME of the given precision;
 * NULL when the operand is. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
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

        return new ScalarFact(new Known(new Temporal(TemporalKind::DateTime, $this->precision)), $fact->nullability);
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
