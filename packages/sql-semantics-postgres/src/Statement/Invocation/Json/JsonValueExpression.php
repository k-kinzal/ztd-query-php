<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\JsonChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A value passed to an SQL/JSON function, with an optional FORMAT clause.
 *
 * Mirrors PostgreSQL's `JsonValueExpr`. Whoever holds it derives it once,
 * through `deriveValue()`, to read the value's facts. An ENCODING on a value
 * that is not `bytea` is reported (PG-JSON-CHECKS-001).
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE.
 *
 * @visibility public
 * @example Reading a value without a format
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()))->format // => null
 */
final class JsonValueExpression implements Clause
{
    use Snapshot;

    /**
     * @param Scalar $value The value
     * @param JsonFormat|null $format The format written
     */
    public function __construct(public readonly Scalar $value, public readonly ?JsonFormat $format = null)
    {
    }

    /**
     * Derives the value and answers its facts.
     *
     * @param bool $parsed Whether the value is parsed as the context item or the operand of JSON(), which words the encoding problem differently
     */
    public function deriveValue(Derivation $derivation, Environment $environment, bool $parsed = false): ScalarFact
    {
        $fact = $derivation->scalar($this->value, $environment);
        (new JsonChecks())->input($derivation, $this, $fact, $parsed);

        return $fact;
    }

    /**
     * Derives the value.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->deriveValue($derivation, $environment);
    }

    /**
     * Writes the value and the format.
     */
    public function render(Output $out): void
    {
        $out->node($this->value)->node($this->format);
    }
}
