<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
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
 * `JSON_SERIALIZE (value [RETURNING type])`: a JSON value as a character or byte string (PostgreSQL 17).
 *
 * Mirrors PostgreSQL's `JsonSerializeExpr`. Rule: PG-JSON-SERIALIZE-001.
 * Facts: the RETURNING type, otherwise `text`; NULL exactly when the value
 * is. The result column is named `json_serialize`.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE. Status: Implemented.
 *
 * @visibility public
 * @example Naming the result column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonSerialize(new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral())))->outputName()->value // => 'json_serialize'
 */
final class JsonSerialize implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param JsonValueExpression $value The JSON value
     * @param JsonReturning|null $returning The RETURNING clause
     */
    public function __construct(public readonly JsonValueExpression $value, public readonly ?JsonReturning $returning = null)
    {
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('json_serialize');
    }

    /**
     * Derives the value and the returning type, and the NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $value = $this->value->deriveValue($derivation, $environment);
        $this->returning?->deriveClause($derivation, $environment);
        $type = $this->returning?->type->typeFact($derivation->context) ?? new Known(Builtin::Text);

        return new ScalarFact($type, $value->type instanceof NullOnly ? Nullability::Nullable : $value->nullability);
    }

    /**
     * Writes JSON_SERIALIZE with the value and the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_SERIALIZE')->glue()->symbol('(')->node($this->value)->node($this->returning)->symbol(')');
    }
}
