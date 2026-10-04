<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

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
 * @visibility public
 * @example Negating a comparison
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT NOT 1 = 2')->field(0)->type->descriptor->name() // => 'boolean'
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
