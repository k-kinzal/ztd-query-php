<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * A truth-value test: `a IS [NOT] TRUE`, `FALSE` or `UNKNOWN`.
 *
 * Mirrors PostgreSQL's `BooleanTest` node.
 *
 * Rule: PG-BOOLEAN-TEST-001. Facts: `boolean`, never NULL; an operand that
 * is not boolean is reported. The operand must keep its place before IS
 * (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-comparison.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class BooleanTest implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested condition
     * @param BooleanTestKind $kind The test
     */
    public function __construct(public readonly Scalar $operand, public readonly BooleanTestKind $kind)
    {
        Check::input((new Precedence())->before($operand, Precedence::IS), 'The tested value needs parentheses to keep its place.');
    }

    /**
     * Derives the operand and checks that it is boolean.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);

        return new ScalarFact((new OperandChecks())->boolean($derivation, $operand->type, $this->kind->value) ?? new Known(Builtin::Bool), Nullability::NotNull);
    }

    /**
     * Writes the value and the test.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword(...explode(' ', $this->kind->value));
    }
}
