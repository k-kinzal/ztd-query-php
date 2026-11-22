<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * `JSON (value [WITH | WITHOUT UNIQUE KEYS])`: a text or byte string parsed as `json` (PostgreSQL 17).
 *
 * Mirrors PostgreSQL's `JsonParseExpr`. Rule: PG-JSON-PARSE-001. Facts:
 * `json`, NULL exactly when the value is. The result column is named `json`.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE. Status: Implemented.
 *
 * @visibility SqlSemantics
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
        Check::input($unique === null || $value->format !== null || !(new Precedence())->takesUniqueness($value->value), 'A value ending in IS JSON without a uniqueness clause would take the uniqueness clause; group it.');
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
