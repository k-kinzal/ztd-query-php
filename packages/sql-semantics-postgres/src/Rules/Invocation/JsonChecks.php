<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Invocation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorClause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonFunctionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQueryFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * Reports the SQL/JSON expressions the server rejects while analyzing them.
 *
 * Rule: PG-JSON-CHECKS-001. An ENCODING is allowed only on a `bytea` value
 * (a string constant or NULL is `text` there). JSON_QUERY accepts ERROR,
 * NULL, EMPTY [ARRAY], EMPTY OBJECT and DEFAULT on empty and on error, and
 * OMIT QUOTES only without a wrapper; JSON_EXISTS accepts ERROR, TRUE, FALSE
 * and UNKNOWN on error; JSON_VALUE accepts ERROR, NULL and DEFAULT; FORMAT
 * JSON in RETURNING is meaningful for JSON_QUERY only. The same behavior
 * rules apply to the columns of JSON_TABLE, named in the message.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING,
 * `transformJsonFuncExpr` and `transformJsonValueExpr` in `src/backend/parser/parse_expr.c` of PostgreSQL 17.
 * Termination: constant work. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class JsonChecks
{
    /**
     * The behaviors each function accepts.
     */
    private const ACCEPTED = [
        'JSON_QUERY' => ['ERROR', 'NULL', 'EMPTY', 'EMPTY ARRAY', 'EMPTY OBJECT', 'DEFAULT'],
        'JSON_EXISTS' => ['ERROR', 'TRUE', 'FALSE', 'UNKNOWN'],
        'JSON_VALUE' => ['ERROR', 'NULL', 'DEFAULT'],
    ];

    /**
     * Reports an ENCODING written on a value that is not `bytea`.
     *
     * @param bool $parsed Whether the value is parsed as a context item or by JSON(), which words the problem differently
     */
    public function input(Derivation $derivation, JsonValueExpression $value, ScalarFact $fact, bool $parsed = false): void
    {
        if ($value->format?->encodingName === null) {
            return;
        }
        $textual = $fact->type instanceof NullOnly || ($fact->type instanceof Known && $fact->type->descriptor !== Builtin::Bytea);
        if ($textual) {
            $derivation->report(new JsonProblem($parsed ? JsonProblemKind::ParsedEncodingWithoutBytea : JsonProblemKind::EncodingWithoutBytea));
        }
    }

    /**
     * Reports the clause combinations and behaviors a query function does not accept.
     */
    public function query(Derivation $derivation, JsonQueryFunction $function): void
    {
        $format = $function->returning?->format;
        if ($format !== null && $function->kind !== JsonFunctionKind::Query) {
            $derivation->report(new JsonProblem(JsonProblemKind::FormatInReturning, [strtolower($function->kind->value)]));
        }
        if ($function->quotes !== null && !$function->quotes->keep && $function->wrapper !== null && $function->wrapper->kind->wraps()) {
            $derivation->report(new JsonProblem(JsonProblemKind::QuotesWithWrapper));
        }
        $this->behaviors($derivation, $function->kind, $function->behavior);
    }

    /**
     * Reports the behaviors a function, or a JSON_TABLE column evaluated like it, does not accept.
     *
     * @param Name|null $column The JSON_TABLE column the behaviors belong to
     */
    public function behaviors(Derivation $derivation, JsonFunctionKind $kind, ?JsonBehaviorClause $clause, ?Name $column = null): void
    {
        foreach (['ON EMPTY' => $clause?->onEmpty, 'ON ERROR' => $clause?->onError] as $position => $behavior) {
            if ($behavior instanceof JsonBehavior && !$this->accepted($kind, $behavior->kind)) {
                $derivation->report($column === null
                    ? new JsonProblem(JsonProblemKind::InvalidBehavior, [$position])
                    : new JsonProblem(JsonProblemKind::InvalidColumnBehavior, [$position, $column->value]));
            }
        }
    }

    /**
     * Tells whether a function accepts a behavior.
     */
    public function accepted(JsonFunctionKind $kind, JsonBehaviorKind $behavior): bool
    {
        return in_array($behavior->value, self::ACCEPTED[$kind->value], true);
    }
}
