<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Unification;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\Coalescing;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

/**
 * `COALESCE (values)`: the first value that is not NULL.
 *
 * Mirrors PostgreSQL's `CoalesceExpr`. Rule: PG-COALESCE-001. Facts: the
 * common type of the values as UNION and CASE resolve it
 * (`Rules\Expression\Unification`); the NULL rule PG-COALESCING-NULL-001.
 * The result column is named `coalesce`.
 * Source: https://www.postgresql.org/docs/17/functions-conditional.html#FUNCTIONS-COALESCE-NVL-IFNULL,
 * https://www.postgresql.org/docs/17/typeconv-union-case.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Naming the result column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\Coalesce([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()]))->outputName()->value // => 'coalesce'
 */
final class Coalesce implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The values in order
     */
    public readonly array $values;

    /**
     * @param list<Scalar> $values The values in order; at least one
     */
    public function __construct(array $values)
    {
        $this->values = Check::listOf($values, Scalar::class, 'COALESCE takes at least one value.', 1);
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('coalesce');
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

        return new ScalarFact((new Unification())->resolve($derivation->context, $types, 'COALESCE'), (new Coalescing())->nullability($facts));
    }

    /**
     * Writes COALESCE with the values.
     */
    public function render(Output $out): void
    {
        $out->keyword('COALESCE')->glue()->symbol('(')->list($this->values)->symbol(')');
    }
}
