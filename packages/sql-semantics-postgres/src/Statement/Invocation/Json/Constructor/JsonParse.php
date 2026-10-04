<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
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
 * `JSON (value [WITH | WITHOUT UNIQUE KEYS])`: a text or byte string parsed as `json` (PostgreSQL 17).
 *
 * Mirrors PostgreSQL's `JsonParseExpr`. Rule: PG-JSON-PARSE-001. Facts:
 * `json`, NULL exactly when the value is. The result column is named `json`.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE. Status: Implemented.
 *
 * @visibility public
 * @example Reading a parse with a uniqueness check
 *     $parse = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonParse(new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()), new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys(true));
 *     [$parse->unique->unique, $parse->outputName()->value] // => [true, 'json']
 */
final class JsonParse implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param JsonValueExpression $value The value parsed
     * @param JsonUniqueKeys|null $unique The uniqueness clause written
     */
    public function __construct(public readonly JsonValueExpression $value, public readonly ?JsonUniqueKeys $unique = null)
    {
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('json');
    }

    /**
     * Derives the value, the type and the NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $value = $this->value->deriveValue($derivation, $environment, true);

        return new ScalarFact(new Known(Builtin::Json), $value->type instanceof NullOnly ? Nullability::Nullable : $value->nullability);
    }

    /**
     * Writes JSON with the value and the uniqueness clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON')->glue()->symbol('(')->node($this->value)->node($this->unique)->symbol(')');
    }
}
