<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\ItemNaming;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
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
 * A selection: projected expressions over an optional table, filtered by an optional predicate.
 *
 * Slice of the query family: it holds a select list of expressions, one
 * table reference and a WHERE predicate, in both grammar generations, and is
 * completed or replaced by that family.
 *
 * Rule: MYSQL-SELECT-001. The table reference is derived in the environment
 * of the enclosing query. The select list and the predicate see the table
 * occurrence and, beyond it, the enclosing query. An item is named by
 * MYSQL-SELECT-ITEM-NAME-001. Diagnostics: those of its parts. Terminates:
 * the parts are strict parts. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of a selection
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a > 1');
 *     [count($query->statement->items), $query->statement->where !== null] // => [1, true]
 * @example Resolving a column to the declaration a context supplies
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $query = $semantics->analyze('SELECT a FROM t');
 *     $query->field('a')->type instanceof \SqlSemantics\Statement\Type\Dependent // => true
 */
final class Select implements Statement, Query, Selection
{
    use Snapshot;

    /**
     * @var non-empty-list<SelectExpression> The projected expressions in output order
     */
    public readonly array $items;

    /**
     * @param list<SelectExpression> $items The projected expressions in output order; at least one
     * @param TableReference|null $from The table reference
     * @param Scalar|null $where The row predicate
     */
    public function __construct(array $items, public readonly ?TableReference $from = null, public readonly ?Scalar $where = null)
    {
        $this->items = Check::listOf($items, SelectExpression::class, 'A selection projects at least one expression.', 1);
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
     * Derives the table reference, the predicate and the output fields.
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
        foreach ($this->items as $position => $item) {
            $fact = $derivation->scalar($item->expression, $environment);
            $origin = $fact->resolution instanceof ResolvedColumn ? $fact->resolution->slot : null;
            $fields[] = new Field($position, new OutputSlot((new ItemNaming())->name($item), $fact->type, $fact->nullability, null, $origin), $item->expression, $fact->resolution);
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
        $out->keyword('SELECT')->list($this->items);
        if ($this->from !== null) {
            $out->keyword('FROM')->node($this->from);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
