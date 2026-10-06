<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonFormat;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * `JSON_ARRAY (query [FORMAT JSON] [RETURNING type])`: the rows of a one-column query as a JSON array.
 *
 * Mirrors PostgreSQL's `JsonArrayQueryConstructor`; the query is written
 * without parentheses. Rule: PG-JSON-ARRAY-QUERY-001. Facts: the query is
 * derived as a subquery at the position; the RETURNING type, otherwise
 * `json`; never NULL. Diagnostics: a query of more than one column. The
 * result column is named `json_array`.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE. Status: Implemented.
 *
 * @visibility public
 * @example Telling the class plays the expression role
 *     is_subclass_of(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonArrayQuery::class, \SqlSemantics\Statement\Scalar::class) // => true
 */
final class JsonArrayQuery implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Query $query The query whose rows become the elements
     * @param JsonFormat|null $format The format written for the query's values
     * @param JsonReturning|null $returning The RETURNING clause
     */
    public function __construct(public readonly Query $query, public readonly ?JsonFormat $format = null, public readonly ?JsonReturning $returning = null)
    {
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('json_array');
    }

    /**
     * Derives the query and the returning type, reporting a query of several columns.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fields = $derivation->query($this->query, $environment)->fields();
        if ($fields !== null && $fields->count() !== 1) {
            $derivation->report(new JsonProblem(JsonProblemKind::SubqueryColumns));
        }
        $this->returning?->deriveClause($derivation, $environment);

        return new ScalarFact($this->returning?->type->typeFact($derivation->context) ?? new Known(Builtin::Json), Nullability::NotNull);
    }

    /**
     * Writes JSON_ARRAY with the query and the clauses.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_ARRAY')->glue()->symbol('(')->node($this->query)->node($this->format)->node($this->returning)->symbol(')');
    }
}
