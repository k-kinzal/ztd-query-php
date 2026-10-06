<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
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

/**
 * `JSON_ARRAY (values [NULL | ABSENT ON NULL] [RETURNING type])`.
 *
 * Mirrors PostgreSQL's `JsonArrayConstructor`. Without values, no NULL
 * clause can be written. Rule: PG-JSON-ARRAY-001. Facts: the RETURNING type,
 * otherwise `json`; never NULL. The result column is named `json_array`.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE. Status: Implemented.
 *
 * @visibility public
 * @example Reading an array constructor
 *     $array = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonArrayConstructor([new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral())], \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling::Keep);
 *     [count($array->values), $array->nulls->value] // => [1, 'NULL']
 */
final class JsonArrayConstructor implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var list<JsonValueExpression> The elements
     */
    public readonly array $values;

    /**
     * @param list<JsonValueExpression> $values The elements
     * @param JsonNullHandling|null $nulls The NULL clause written
     * @param JsonReturning|null $returning The RETURNING clause
     */
    public function __construct(array $values, public readonly ?JsonNullHandling $nulls = null, public readonly ?JsonReturning $returning = null)
    {
        $this->values = Check::listOf($values, JsonValueExpression::class, 'JSON_ARRAY takes JSON values.');
        Check::input($this->values !== [] || $nulls === null, 'JSON_ARRAY without values takes no NULL clause.');
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('json_array');
    }

    /**
     * Derives the elements and the returning type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        foreach ($this->values as $value) {
            $value->deriveValue($derivation, $environment);
        }
        $this->returning?->deriveClause($derivation, $environment);

        return new ScalarFact($this->returning?->type->typeFact($derivation->context) ?? new Known(Builtin::Json), Nullability::NotNull);
    }

    /**
     * Writes JSON_ARRAY with its elements and clauses.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_ARRAY')->glue()->symbol('(')->list($this->values);
        if ($this->nulls !== null) {
            $out->keyword($this->nulls->value, 'ON', 'NULL');
        }
        $out->node($this->returning)->symbol(')');
    }
}
