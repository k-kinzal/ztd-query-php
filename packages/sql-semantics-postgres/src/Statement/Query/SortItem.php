<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;

/**
 * One sort key of an ORDER BY list: an expression, a direction or ordering operator, and the place of NULLs.
 *
 * Mirrors PostgreSQL's `SortBy` node. It is shared by queries, aggregate
 * calls, window specifications and WITHIN GROUP. The holder derives the
 * expression in the environment its position is evaluated in.
 * Source: https://www.postgresql.org/docs/17/queries-order.html, https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY.
 *
 * @visibility SqlSemantics
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
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('<')),
 *     ) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
