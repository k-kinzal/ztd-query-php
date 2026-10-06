<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Unification;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\Coalescing;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * `GREATEST (values)` or `LEAST (values)`.
 *
 * Mirrors PostgreSQL's `MinMaxExpr`. Rule: PG-MINMAX-001. Facts: the
 * common type of the values (`Rules\Expression\Unification`); NULL values
 * are ignored, so the NULL rule is PG-COALESCING-NULL-001. The result column
 * is named `greatest` or `least`.
 * Source: https://www.postgresql.org/docs/17/functions-conditional.html#FUNCTIONS-GREATEST-LEAST. Status: Implemented.
 *
 * @visibility public
 * @example Naming the result column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\MinMax(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\MinMaxKind::Greatest, [new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()]))->outputName()->value // => 'greatest'
 */
final class MinMax implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The values in order
     */
    public readonly array $values;

    /**
     * @param MinMaxKind $kind Which extreme is selected
     * @param list<Scalar> $values The values in order; at least one
     */
    public function __construct(public readonly MinMaxKind $kind, array $values)
    {
        $this->values = Check::listOf($values, Scalar::class, 'GREATEST and LEAST take at least one value.', 1);
    }

    /**
     * Names an unaliased result column after the function.
     */
    public function outputName(): Name
    {
        return new Name(strtolower($this->kind->value));
    }

    /**
     * Derives the values, their common type and the NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [];
        $types = [];
        foreach ($this->values as $value) {
            $fact = $derivation->scalar($value, $environment);
            $facts[] = $fact;
            $types[] = $fact->type;
        }

        return new ScalarFact((new Unification())->resolve($derivation->context, $types, $this->kind->value), (new Coalescing())->nullability($facts));
    }

    /**
     * Writes the function keyword with the values.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->glue()->symbol('(')->list($this->values)->symbol(')');
    }
}
