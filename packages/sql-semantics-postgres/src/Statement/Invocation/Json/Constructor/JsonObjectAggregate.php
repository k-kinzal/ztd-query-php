<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonPair;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
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
 * `JSON_OBJECTAGG (key : value [NULL | ABSENT ON NULL] [WITH | WITHOUT UNIQUE] [RETURNING type])` with FILTER and OVER.
 *
 * Mirrors PostgreSQL's `JsonObjectAgg` with its `JsonAggConstructor`.
 * Rule: PG-JSON-OBJECTAGG-001. Facts: the pair, the filter and the window are
 * derived at the position; the RETURNING type, otherwise `json`; NULL over
 * no row. The result column is named `json_objectagg`.
 * Source: https://www.postgresql.org/docs/17/functions-aggregate.html. Status: Implemented.
 *
 * @visibility public
 * @example Naming the result column
 *     $pair = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonPair(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()));
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonObjectAggregate($pair))->outputName()->value // => 'json_objectagg'
 */
final class JsonObjectAggregate implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param JsonPair $pair The key and value aggregated
     * @param JsonNullHandling|null $nulls The NULL clause written
     * @param JsonUniqueKeys|null $unique The uniqueness clause written
     * @param JsonReturning|null $returning The RETURNING clause
     * @param Scalar|null $filter The FILTER predicate
     * @param WindowSpecification|Name|null $over The window after OVER
     */
    public function __construct(
        public readonly JsonPair $pair,
        public readonly ?JsonNullHandling $nulls = null,
        public readonly ?JsonUniqueKeys $unique = null,
        public readonly ?JsonReturning $returning = null,
        public readonly ?Scalar $filter = null,
        public readonly WindowSpecification|Name|null $over = null,
    ) {
        Check::input($unique === null || $nulls !== null || $pair->value->format !== null || !(new Precedence())->takesUniqueness($pair->value->value), 'A value ending in IS JSON without a uniqueness clause would take the uniqueness clause; group it.');
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('json_objectagg');
    }

    /**
     * Derives the pair, the clauses, the filter and the window.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $this->pair->deriveClause($derivation, $environment);
        $this->returning?->deriveClause($derivation, $environment);
        if ($this->filter !== null) {
            $derivation->scalar($this->filter, $environment);
        }
        if ($this->over instanceof WindowSpecification) {
            $this->over->deriveClause($derivation, $environment);
        }

        return new ScalarFact($this->returning?->type->typeFact($derivation->context) ?? new Known(Builtin::Json), Nullability::Nullable);
    }

    /**
     * Writes JSON_OBJECTAGG with its clauses, the filter and the window.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_OBJECTAGG')->glue()->symbol('(')->node($this->pair);
        if ($this->nulls !== null) {
            $out->keyword($this->nulls->value, 'ON', 'NULL');
        }
        $out->node($this->unique)->node($this->returning)->symbol(')');
        if ($this->filter !== null) {
            $out->keyword('FILTER')->symbol('(')->keyword('WHERE')->node($this->filter)->symbol(')');
        }
        if ($this->over instanceof Name) {
            $out->keyword('OVER')->name($this->over, NameUse::Column);
        } elseif ($this->over !== null) {
            $out->keyword('OVER')->node($this->over);
        }
    }
}
