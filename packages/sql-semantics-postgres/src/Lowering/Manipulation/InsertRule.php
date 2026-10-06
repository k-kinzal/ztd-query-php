<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Manipulation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\ColumnTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoNothing;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoUpdate;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConstraintInference;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\IndexInference;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertDefaults;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Overriding;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\ParenthesizedRows;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\ValueRows;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
use SqlSemantics\Statement\Query;

/**
 * Lowers INSERT.
 *
 * Rule: PG-INSERT-LOWER-001. Scope: `InsertStmt`, `insert_target`,
 * `insert_rest`, `override_kind`, `insert_column_list`,
 * `insert_column_item`, `opt_on_conflict`, `opt_conf_expr`. Constructors:
 * `InsertRows` when the query is VALUES alone, in parentheses or none (the
 * grammar keeps no node for them), `InsertSelect` for any other query,
 * `InsertDefaults` for DEFAULT VALUES; `TargetTable`, `ColumnTarget`,
 * `Overriding`, `ConflictDoNothing`, `ConflictDoUpdate`, `IndexInference`,
 * `ConstraintInference`. Termination: lists are flattened iteratively;
 * parentheses around VALUES are unwrapped one per level.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class InsertRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `InsertStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function insert(Node $statement): InsertRows|InsertSelect|InsertDefaults
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'InsertStmt: opt_with_clause INSERT INTO insert_target insert_rest opt_on_conflict returning_clause') {
            throw ImplementationGap::production($form);
        }
        $with = $this->lowering->queries->with($form->node(0));
        $target = $this->target($form->node(3));
        $rest = $this->lowering->productions->form($form->node(4));
        if ($rest->signature === 'insert_rest: DEFAULT VALUES') {
            return new InsertDefaults($with, $target, $this->conflict($form->node(5)), (new ChangeRule($this->lowering))->returning($form->node(6)));
        }
        [$columns, $overriding, $query] = $this->rest($rest);
        $conflict = $this->conflict($form->node(5));
        $returning = (new ChangeRule($this->lowering))->returning($form->node(6));
        $rows = $this->rows($query);
        if ($rows !== null) {
            return new InsertRows($with, $target, $columns, $overriding, $rows, $conflict, $returning);
        }

        return new InsertSelect($with, $target, $columns, $overriding, $query, $conflict, $returning);
    }

    /**
     * Lowers the forms of `insert_rest` that hold a query: the column list, the override and the query.
     *
     * @return array{list<ColumnTarget>, Overriding|null, Query}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function rest(Form $rest): array
    {
        return match ($rest->signature) {
            'insert_rest: SelectStmt' => [[], null, $this->lowering->queries->query($rest->node(0))],
            'insert_rest: OVERRIDING override_kind VALUE_P SelectStmt' => [[], $this->overriding($rest->node(1)), $this->lowering->queries->query($rest->node(3))],
            'insert_rest: ( insert_column_list ) SelectStmt' => [$this->columns($rest->node(1)), null, $this->lowering->queries->query($rest->node(3))],
            'insert_rest: ( insert_column_list ) OVERRIDING override_kind VALUE_P SelectStmt' => [
                $this->columns($rest->node(1)),
                $this->overriding($rest->node(4)),
                $this->lowering->queries->query($rest->node(6)),
            ],
            default => throw ImplementationGap::production($rest),
        };
    }

    /**
     * Answers the VALUES rows a query is, when it is VALUES alone in parentheses or none; otherwise null.
     */
    public function rows(Query $query): ValueRows|ParenthesizedRows|null
    {
        $depth = 0;
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
            $depth++;
        }
        if (!$query instanceof ValuesList) {
            return null;
        }
        $rows = new ValueRows($query->rows);
        for (; $depth > 0; $depth--) {
            $rows = new ParenthesizedRows($rows);
        }

        return $rows;
    }

    /**
     * Lowers `insert_target`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function target(Node $target): TargetTable
    {
        $form = $this->lowering->productions->form($target);

        return match ($form->signature) {
            'insert_target: qualified_name' => new TargetTable(new RelationReference($this->lowering->names->qualified($form->node(0)))),
            'insert_target: qualified_name AS ColId' => new TargetTable(new RelationReference($this->lowering->names->qualified($form->node(0))), $this->lowering->names->name($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `override_kind`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function overriding(Node $kind): Overriding
    {
        $form = $this->lowering->productions->form($kind);

        return match ($form->signature) {
            'override_kind: USER' => Overriding::User,
            'override_kind: SYSTEM_P' => Overriding::System,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `insert_column_list`.
     *
     * @return list<ColumnTarget>
     *
     * @throws ImplementationGap When an item has no rule
     */
    public function columns(Node $list): array
    {
        $columns = [];
        foreach ($this->lowering->items($list, 'insert_column_list: insert_column_item', 'insert_column_list: insert_column_list , insert_column_item') as $item) {
            $form = $this->lowering->productions->form($item);
            if ($form->signature !== 'insert_column_item: ColId opt_indirection') {
                throw ImplementationGap::production($form);
            }
            $columns[] = new ColumnTarget($this->lowering->names->name($form->node(0)), $this->lowering->expressions->indirection($form->node(1)));
        }

        return $columns;
    }

    /**
     * Lowers `opt_on_conflict`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function conflict(Node $clause): ConflictDoNothing|ConflictDoUpdate|null
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_on_conflict: ON CONFLICT opt_conf_expr DO UPDATE SET set_clause_list where_clause' => new ConflictDoUpdate(
                $this->inference($form->node(2)),
                (new ChangeRule($this->lowering))->assignments($form->node(6)),
                $this->lowering->queries->where($form->node(7)),
            ),
            'opt_on_conflict: ON CONFLICT opt_conf_expr DO NOTHING' => new ConflictDoNothing($this->inference($form->node(2))),
            'opt_on_conflict:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_conf_expr`; no target is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function inference(Node $target): IndexInference|ConstraintInference|null
    {
        $form = $this->lowering->productions->form($target);

        return match ($form->signature) {
            'opt_conf_expr: ( index_params ) where_clause' => new IndexInference($this->lowering->tables->indexParameters($form->node(1)), $this->lowering->queries->where($form->node(3))),
            'opt_conf_expr: ON CONSTRAINT name' => new ConstraintInference($this->lowering->names->name($form->node(2))),
            'opt_conf_expr:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
