<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Selection;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A selection: projected expressions over an optional input, filtered by an optional predicate.
 *
 * Rule: SQLITE-SELECT-001. The input relation is derived in the environment
 * of the enclosing query. Projection and predicate see the input occurrence
 * and, beyond it, the enclosing query. A projected column use is named after
 * its column; an alias names any expression; other expressions have no fixed
 * name here. Source: https://sqlite.org/lang_select.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of a selection
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t WHERE a > 1');
 *     [count($query->statement->columns), $query->statement->where !== null] // => [1, true]
 */
final class Select implements Statement, Query, Selection
{
    use Snapshot;

    /**
     * @var non-empty-list<ResultColumn> The projected expressions in output order
     */
    public readonly array $columns;

    /**
     * @param list<ResultColumn> $columns The projected expressions in output order; at least one
     * @param TableInput|null $from The input relation
     * @param Scalar|null $where The row predicate
     */
    public function __construct(array $columns, public readonly ?TableInput $from = null, public readonly ?Scalar $where = null)
    {
        $this->columns = Check::listOf($columns, ResultColumn::class, 'A selection projects at least one result column.', 1);
    }

    /**
     * Answers the input relation.
     */
    public function input(): ?Relation
    {
        return $this->from;
    }

    /**
     * Derives the selection as a statement root and records its rows as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->output($derivation->query($this, $derivation->environment()));
    }

    /**
     * Derives the input, the predicate and the output fields.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        $relations = [];
        if ($this->from !== null) {
            $fact = $derivation->relation($this->from, $outer);
            $relations[] = new VisibleRelation($this->from, $fact->shape, $this->from->alias, $this->from->name);
        }
        $environment = new Environment($derivation->context, $outer, $relations);
        $fields = [];
        foreach ($this->columns as $position => $column) {
            $fact = $derivation->scalar($column->expression, $environment);
            $name = $column->alias ?? ($column->expression instanceof ColumnUse ? $column->expression->name : null);
            $origin = $fact->resolution instanceof ResolvedColumn ? $fact->resolution->slot : null;
            $fields[] = new Field($position, new OutputSlot($name, $fact->type, $fact->nullability, null, $origin), $column->expression, $fact->resolution);
        }
        if ($this->where !== null) {
            $derivation->scalar($this->where, $environment);
        }

        return new QueryFact($fields, $derivation->context->columnNames);
    }

    /**
     * Writes the clauses in grammar order.
     */
    public function render(Output $out): void
    {
        $out->keyword('SELECT')->list($this->columns);
        if ($this->from !== null) {
            $out->keyword('FROM')->node($this->from);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
