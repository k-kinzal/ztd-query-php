<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\PredicateTyping;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A time converted to or from a time zone: `a AT TIME ZONE zone`.
 *
 * The parser reads it as a call of `pg_catalog.timezone(zone, a)`.
 *
 * Rule: PG-AT-TIME-ZONE-001. Facts: `timestamp with time zone` gives
 * `timestamp`, `timestamp` gives `timestamp with time zone`, a time with or
 * without zone gives `time with time zone`, and an unknown-typed constant
 * is read as the preferred `timestamp with time zone`; the zone is text or an
 * interval; NULL when an operand can be. Both operands must keep their
 * place (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-datetime.html#FUNCTIONS-DATETIME-ZONECONVERT. Status: Implemented.
 *
 * @visibility public
 * @example Converting a timestamp with time zone
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT '2024-01-01 00:00+00'::timestamptz AT TIME ZONE 'UTC'", [])->field(0)->type->descriptor->name() // => 'timestamp without time zone'
 */
final class AtTimeZone implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The time value
     * @param Scalar $zone The time zone
     */
    public function __construct(public readonly Scalar $operand, public readonly Scalar $zone)
    {
        $precedence = new Precedence();
        Check::input($precedence->before($operand, Precedence::AT), 'The time value needs parentheses to keep its place.');
        Check::input($precedence->after($zone, Precedence::AT), 'The time zone needs parentheses to keep its place.');
    }

    /**
     * Derives the operands and the converted type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $zone = $derivation->scalar($this->zone, $environment);

        return new ScalarFact((new PredicateTyping())->zone($derivation->context, $operand->type, $zone->type), (new OperandChecks())->nullability([$operand, $zone]));
    }

    /**
     * Writes the value, AT TIME ZONE and the zone.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('AT', 'TIME', 'ZONE')->node($this->zone);
    }
}
