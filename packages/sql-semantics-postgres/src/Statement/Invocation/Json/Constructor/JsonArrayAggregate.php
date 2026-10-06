<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
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
 * `JSON_ARRAYAGG (value [ORDER BY ...] [NULL | ABSENT ON NULL] [RETURNING type])` with FILTER and OVER.
 *
 * Mirrors PostgreSQL's `JsonArrayAgg` with its `JsonAggConstructor`.
 * Rule: PG-JSON-ARRAYAGG-001. Facts: the value, the ordering, the filter and
 * the window are derived at the position; the RETURNING type, otherwise
 * `json`; NULL over no row. The result column is named `json_arrayagg`.
 * Source: https://www.postgresql.org/docs/17/functions-aggregate.html. Status: Implemented.
 *
 * @visibility public
 * @example Naming the result column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonArrayAggregate(new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral())))->outputName()->value // => 'json_arrayagg'
 */
final class JsonArrayAggregate implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var list<SortItem> The ordering of the elements
     */
    public readonly array $order;

    /**
     * @param JsonValueExpression $value The value aggregated
     * @param list<SortItem> $order The ordering of the elements
     * @param JsonNullHandling|null $nulls The NULL clause written
     * @param JsonReturning|null $returning The RETURNING clause
     * @param Scalar|null $filter The FILTER predicate
     * @param WindowSpecification|Name|null $over The window after OVER
     */
    public function __construct(
        public readonly JsonValueExpression $value,
        array $order = [],
        public readonly ?JsonNullHandling $nulls = null,
        public readonly ?JsonReturning $returning = null,
        public readonly ?Scalar $filter = null,
        public readonly WindowSpecification|Name|null $over = null,
    ) {
        $this->order = Check::listOf($order, SortItem::class, 'An aggregate ordering is a list of sort items.');
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('json_arrayagg');
    }

    /**
     * Derives the value, the ordering, the clauses, the filter and the window.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $this->value->deriveValue($derivation, $environment);
        foreach ($this->order as $item) {
            $item->deriveClause($derivation, $environment);
        }
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
     * Writes JSON_ARRAYAGG with its clauses, the filter and the window.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_ARRAYAGG')->glue()->symbol('(')->node($this->value);
        if ($this->order !== []) {
            $out->keyword('ORDER', 'BY')->list($this->order);
        }
        if ($this->nulls !== null) {
            $out->keyword($this->nulls->value, 'ON', 'NULL');
        }
        $out->node($this->returning)->symbol(')');
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
