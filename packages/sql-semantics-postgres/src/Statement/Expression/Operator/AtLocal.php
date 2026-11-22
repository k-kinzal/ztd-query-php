<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\PredicateTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * A time converted to or from the session time zone: `a AT LOCAL` (PostgreSQL 17).
 *
 * The parser reads it as a call of `pg_catalog.timezone(a)`.
 *
 * Rule: PG-AT-LOCAL-001. Facts: those of PG-AT-TIME-ZONE-001 with a text
 * zone; the value depends on the session's TimeZone setting, not its type.
 * The operand must keep its place (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-datetime.html#FUNCTIONS-DATETIME-ZONECONVERT. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AtLocal implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The time value
     */
    public function __construct(public readonly Scalar $operand)
    {
        Check::input((new Precedence())->before($operand, Precedence::AT), 'The time value needs parentheses to keep its place.');
    }

    /**
     * Derives the operand and the converted type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);

        return new ScalarFact((new PredicateTyping())->zone($derivation->context, $operand->type, new Known(Builtin::Text)), $operand->nullability);
    }

    /**
     * Writes the value and AT LOCAL.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('AT', 'LOCAL');
    }
}
