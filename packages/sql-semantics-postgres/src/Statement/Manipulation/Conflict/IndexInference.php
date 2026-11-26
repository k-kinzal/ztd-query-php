<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * The conflict target `( index_column_or_expression, ... ) [ WHERE predicate ]` that infers the unique indexes ON CONFLICT arbitrates on.
 *
 * Mirrors PostgreSQL's `InferClause` with `indexElems` and `whereClause`.
 * The elements and the predicate see the target table only.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html#SQL-ON-CONFLICT.
 *
 * @visibility SqlSemantics
 * @example Refusing an empty list of index elements
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\IndexInference([]) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class IndexInference implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Clause> The index columns and expressions, in order
     */
    public readonly array $elements;

    /**
     * @param list<Clause> $elements The index columns and expressions, in order; at least one
     * @param Scalar|null $where The predicate of the partial unique indexes inferred
     *
     * @throws InvalidConstruction When there is no element
     */
    public function __construct(array $elements, public readonly ?Scalar $where = null)
    {
        $this->elements = Check::listOf($elements, Clause::class, 'A conflict target names at least one index element.', 1);
    }

    /**
     * Writes the parenthesized elements and the predicate.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->elements)->symbol(')');
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
