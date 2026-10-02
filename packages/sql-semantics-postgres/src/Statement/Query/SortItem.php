<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One sort key of an ORDER BY list: an expression, a direction or ordering operator, and the place of NULLs.
 *
 * Mirrors PostgreSQL's `SortBy` node. It is shared by queries, aggregate
 * calls, window specifications and WITHIN GROUP. The holder derives the
 * expression in the environment its position is evaluated in.
 * Source: https://www.postgresql.org/docs/17/queries-order.html, https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY.
 *
 * @visibility public
 * @example Reading a descending sort key
 *     $item = new \SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')),
 *         \SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection::Descending,
 *     );
 *     [$item->direction->value, $item->nulls] // => ['DESC', null]
 * @example Rejecting a direction next to an ordering operator
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(),
 *         \SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection::Ascending,
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Statement\Identifier\Name('<')),
 *     ) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class SortItem implements Clause
{
    use Snapshot;

    /**
     * @param Scalar $expression The sort key
     * @param SortDirection|null $direction The direction written
     * @param OperatorName|null $using The ordering operator written with USING
     * @param NullsOrder|null $nulls The place of NULLs written
     */
    public function __construct(
        public readonly Scalar $expression,
        public readonly ?SortDirection $direction = null,
        public readonly ?OperatorName $using = null,
        public readonly ?NullsOrder $nulls = null,
    ) {
        Check::input($direction === null || $using === null, 'A sort key has a direction or an ordering operator, not both.');
    }

    /**
     * Derives the sort key.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->expression, $environment);
    }

    /**
     * Writes the key, the direction or operator, and the place of NULLs.
     */
    public function render(Output $out): void
    {
        $out->node($this->expression);
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
        if ($this->using !== null) {
            $out->keyword('USING')->node($this->using);
        }
        if ($this->nulls !== null) {
            $out->keyword('NULLS', $this->nulls->value);
        }
    }
}
