<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

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
 * @visibility public
 * @example A truth-value test is never NULL
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT NULL IS TRUE')->field(0)->nullability // => \SqlSemantics\Statement\Type\Nullability::NotNull
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
