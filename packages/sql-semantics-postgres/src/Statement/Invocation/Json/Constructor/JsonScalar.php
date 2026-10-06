<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * `JSON_SCALAR (value)`: an SQL scalar as a JSON scalar (PostgreSQL 17).
 *
 * Mirrors PostgreSQL's `JsonScalarExpr`. Rule: PG-JSON-SCALAR-001. Facts:
 * `json`, NULL exactly when the value is. The result column is named
 * `json_scalar`.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE. Status: Implemented.
 *
 * @visibility public
 * @example Naming the result column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonScalar(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()))->outputName()->value // => 'json_scalar'
 */
final class JsonScalar implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $value The SQL value
     */
    public function __construct(public readonly Scalar $value)
    {
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('json_scalar');
    }

    /**
     * Derives the value, the type and the NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $value = $derivation->scalar($this->value, $environment);

        return new ScalarFact(new Known(Builtin::Json), $value->type instanceof NullOnly ? Nullability::Nullable : $value->nullability);
    }

    /**
     * Writes JSON_SCALAR with the value.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_SCALAR')->glue()->symbol('(')->node($this->value)->symbol(')');
    }
}
