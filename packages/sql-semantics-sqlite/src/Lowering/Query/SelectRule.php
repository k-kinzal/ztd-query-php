<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Rules\Query\Ordinals;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundStep;
use SqlSemantics\Platform\Sqlite\Statement\Query\Limit;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers query productions into selections, VALUES clauses, compound queries and queries with common tables.
 *
 * Rule: SQLITE-SELECT-LOWER-001. Scope: select, selectnowith,
 * multiselect_op, oneselect, groupby_opt, having_opt, limit_opt. The arms of
 * a compound query keep their written order; the ORDER BY and LIMIT written
 * after its last arm become those of the compound query. In ORDER BY and
 * GROUP BY an integer constant becomes a result column position
 * (SQLITE-ORDINAL-001). Terminates: the arm list is flattened iteratively;
 * every other child is a strict subtree.
 * Source: https://sqlite.org/lang_select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SelectRule
{
    /**
     * The signature of a selection without a WINDOW clause.
     */
    private const PLAIN = 'oneselect: SELECT distinct selcollist from where_opt groupby_opt having_opt orderby_opt limit_opt';

    /**
     * The signature of a selection with a WINDOW clause.
     */
    private const WINDOWED = 'oneselect: SELECT distinct selcollist from where_opt groupby_opt having_opt window_clause orderby_opt limit_opt';

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a complete query.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function select(Node $select): Select|ValuesClause|Compound|WithQuery
    {
        $form = $this->lowering->productions->form($select);

        return match ($form->signature) {
            'select: selectnowith' => $this->body($form->node(0)),
            'select: WITH wqlist selectnowith' => new WithQuery($this->lowering->commonTables->clause($form->node(1), false), $this->body($form->node(2))),
            'select: WITH RECURSIVE wqlist selectnowith' => new WithQuery($this->lowering->commonTables->clause($form->node(2), true), $this->body($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `selectnowith`: one arm, or a compound query of its arms in written order.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function body(Node $body): Select|ValuesClause|Compound
    {
        $form = $this->lowering->productions->form($body);
        if ($form->signature === 'selectnowith: oneselect') {
            return $this->arm($form->node(0), true);
        }
        if ($form->signature !== 'selectnowith: selectnowith multiselect_op oneselect') {
            throw ImplementationGap::production($form);
        }
        $items = (new Lists())->items($body);
        $steps = [];
        for ($index = 1; $index < count($items); $index += 2) {
            $steps[] = new CompoundStep($this->operator($items[$index]), $this->arm($items[$index + 1], $index + 2 < count($items)));
        }
        $first = $this->arm($items[0], true);
        $last = $this->lowering->productions->form($items[count($items) - 1]);
        $shift = $last->signature === self::WINDOWED ? 1 : 0;
        if ($last->signature !== self::PLAIN && $last->signature !== self::WINDOWED) {
            return new Compound($first, $steps);
        }

        return new Compound($first, $steps, $this->lowering->ordering->orderBy($last->node(7 + $shift), true), $this->limit($last->node(8 + $shift)));
    }

    /**
     * Lowers a `multiselect_op`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function operator(Node $operator): CompoundOperator
    {
        $form = $this->lowering->productions->form($operator);

        return match ($form->signature) {
            'multiselect_op: UNION' => CompoundOperator::Union,
            'multiselect_op: UNION ALL' => CompoundOperator::UnionAll,
            'multiselect_op: EXCEPT|INTERSECT' => CompoundOperator::from(strtoupper($form->token(0)->text)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `oneselect`: a selection or a VALUES clause.
     *
     * @param bool $ordered Whether the ORDER BY and LIMIT of a selection stay on it; false for the last arm of a compound query
     * @throws ImplementationGap When the production has no rule
     */
    public function arm(Node $arm, bool $ordered): Select|ValuesClause
    {
        $form = $this->lowering->productions->form($arm);

        return match ($form->signature) {
            self::PLAIN => $this->selection($form, 0, $ordered),
            self::WINDOWED => $this->selection($form, 1, $ordered),
            'oneselect: values', 'oneselect: mvalues' => $this->lowering->results->values($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a selection from its clauses.
     *
     * @param int $shift The number of positions the WINDOW clause moves the ORDER BY and LIMIT clauses by
     */
    public function selection(Form $form, int $shift, bool $ordered): Select
    {
        return new Select(
            $this->lowering->results->columns($form->node(2)),
            $this->lowering->inputs->from($form->node(3)),
            $this->lowering->expressions->where($form->node(4)),
            $this->groups($form->node(5)),
            $this->having($form->node(6)),
            $shift === 1 ? $this->lowering->windows->definitions($form->node(7)) : [],
            $ordered ? $this->lowering->ordering->orderBy($form->node(7 + $shift), true) : [],
            $ordered ? $this->limit($form->node(8 + $shift)) : null,
            $this->lowering->results->quantifier($form->node(1)),
        );
    }

    /**
     * Lowers a `groupby_opt`: the terms, with integer constants as result column positions.
     *
     * @return list<Scalar>
     * @throws ImplementationGap When the production has no rule
     */
    public function groups(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'groupby_opt:') {
            return [];
        }
        if ($form->signature !== 'groupby_opt: GROUP BY nexprlist') {
            throw ImplementationGap::production($form);
        }
        $terms = [];
        foreach ($this->lowering->expressions->items($form->node(2)) as $term) {
            $terms[] = (new Ordinals())->value($term) === null ? $term : new OutputOrdinal($term);
        }

        return $terms;
    }

    /**
     * Lowers a `having_opt`: the predicate, or null when the clause is absent.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function having(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'having_opt:' => null,
            'having_opt: HAVING expr' => $this->lowering->expressions->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `limit_opt`: the limit in the spelling written, or null when the clause is absent.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function limit(Node $clause): ?Limit
    {
        $form = $this->lowering->productions->form($clause);
        $expressions = $this->lowering->expressions;
        if ($form->signature === 'limit_opt: LIMIT expr COMMA expr') {
            $offset = $expressions->expression($form->node(1));

            return new Limit($expressions->expression($form->node(3)), $offset, true);
        }

        return match ($form->signature) {
            'limit_opt:' => null,
            'limit_opt: LIMIT expr' => new Limit($expressions->expression($form->node(1))),
            'limit_opt: LIMIT expr OFFSET expr' => new Limit($expressions->expression($form->node(1)), $expressions->expression($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }
}
