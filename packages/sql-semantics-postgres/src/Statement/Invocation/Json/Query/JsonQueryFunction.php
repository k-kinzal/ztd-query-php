<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\JsonChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * JSON_EXISTS, JSON_QUERY or JSON_VALUE (PostgreSQL 17): a JSON path applied to a value.
 *
 * Mirrors PostgreSQL's `JsonFuncExpr`. JSON_EXISTS takes no RETURNING,
 * wrapper, quotes or ON EMPTY; JSON_VALUE takes no wrapper or quotes.
 * Rule: PG-JSON-QUERY-001. Facts: the RETURNING type, otherwise `boolean`,
 * `jsonb` or `text`; the result may be NULL. Diagnostics: the behaviors and
 * clause combinations the server rejects (PG-JSON-CHECKS-001). The result
 * column is named after the function in lower case.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING. Status: Implemented.
 *
 * @visibility public
 * @example Reading a JSON_EXISTS call
 *     $exists = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQueryFunction(
 *         \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonFunctionKind::Exists,
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()),
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('$.a')),
 *     );
 *     $exists->outputName()->value // => 'json_exists'
 */
final class JsonQueryFunction implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var list<JsonArgument> The path variables passed
     */
    public readonly array $passing;

    /**
     * @param JsonFunctionKind $kind The function
     * @param JsonValueExpression $context The JSON value the path applies to
     * @param Scalar $path The JSON path
     * @param list<JsonArgument> $passing The path variables passed
     * @param JsonReturning|null $returning The RETURNING clause
     * @param JsonWrapping|null $wrapper The wrapper clause of JSON_QUERY
     * @param JsonQuoting|null $quotes The quotes clause of JSON_QUERY
     * @param JsonBehaviorClause|null $behavior The ON EMPTY and ON ERROR behaviors
     */
    public function __construct(
        public readonly JsonFunctionKind $kind,
        public readonly JsonValueExpression $context,
        public readonly Scalar $path,
        array $passing = [],
        public readonly ?JsonReturning $returning = null,
        public readonly ?JsonWrapping $wrapper = null,
        public readonly ?JsonQuoting $quotes = null,
        public readonly ?JsonBehaviorClause $behavior = null,
    ) {
        $this->passing = Check::listOf($passing, JsonArgument::class, 'PASSING holds path variables.');
        Check::input($kind === JsonFunctionKind::Query || ($wrapper === null && $quotes === null), 'Only JSON_QUERY takes a wrapper or quotes clause.');
        Check::input($kind !== JsonFunctionKind::Exists || ($returning === null && $behavior?->onEmpty === null), 'JSON_EXISTS takes no RETURNING and no ON EMPTY.');
        Check::input($wrapper === null || $this->passing !== [] || $returning !== null || !(new Precedence())->takesUniqueness($path), 'A path ending in IS JSON without a uniqueness clause would take the wrapper clause; group it.');
    }

    /**
     * Names an unaliased result column after the function.
     */
    public function outputName(): Name
    {
        return new Name(strtolower($this->kind->value));
    }

    /**
     * Derives the operands and clauses, reports rejected combinations, and derives the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $this->context->deriveValue($derivation, $environment, true);
        $derivation->scalar($this->path, $environment);
        foreach ($this->passing as $argument) {
            $argument->deriveClause($derivation, $environment);
        }
        $this->returning?->deriveClause($derivation, $environment);
        $this->behavior?->deriveClause($derivation, $environment);
        (new JsonChecks())->query($derivation, $this);
        $type = $this->returning === null ? new Known($this->kind->builtin()) : $this->returning->type->typeFact($derivation->context);

        return new ScalarFact($type, Nullability::Nullable);
    }

    /**
     * Writes the function with its clauses in the order of the grammar.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->glue()->symbol('(')->node($this->context)->symbol(',')->node($this->path);
        if ($this->passing !== []) {
            $out->keyword('PASSING')->list($this->passing);
        }
        $out->node($this->returning)->node($this->wrapper)->node($this->quotes)->node($this->behavior)->symbol(')');
    }
}
