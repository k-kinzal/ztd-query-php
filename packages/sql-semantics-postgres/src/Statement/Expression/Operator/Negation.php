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
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * The negation of a condition: `NOT a`.
 *
 * Mirrors PostgreSQL's `BoolExpr` node of kind `NOT_EXPR`.
 *
 * Rule: PG-NEGATION-001. Facts: `boolean`; a non-boolean operand is
 * reported; NULL when the operand is NULL. The operand must keep its place
 * without parentheses (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-logical.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Negation implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The negated condition
     */
    public function __construct(public readonly Scalar $operand)
    {
        Check::input((new Precedence())->after($operand, Precedence::NOT), 'The operand of NOT needs parentheses to keep its place.');
    }

    /**
     * Derives the condition and checks that it is boolean.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $checks = new OperandChecks();

        return new ScalarFact($checks->boolean($derivation, $operand->type, 'NOT') ?? new Known(Builtin::Bool), $checks->nullability([$operand]));
    }

    /**
     * Writes NOT and the condition.
     */
    public function render(Output $out): void
    {
        $out->keyword('NOT')->node($this->operand);
    }
}
