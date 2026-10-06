<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Manipulation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\Assignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\ColumnTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\RowAssignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\CurrentOf;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers UPDATE and DELETE, and the clauses the data-modifying statements share.
 *
 * Rule: PG-CHANGE-LOWER-001. Scope: `UpdateStmt`, `DeleteStmt`,
 * `using_clause`, `set_clause_list`, `set_clause`, `set_target`,
 * `set_target_list`, `relation_expr_opt_alias`, `where_or_current_clause`,
 * `returning_clause`, `cursor_name`. Constructors: `Update`, `Delete`,
 * `TargetTable`, `Assignment`, `RowAssignment`, `ColumnTarget`,
 * `CurrentOf`. The AS before a correlation name is optional. Termination:
 * lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-update.html, https://www.postgresql.org/docs/17/sql-delete.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ChangeRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `UpdateStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function update(Node $statement): Update
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'UpdateStmt: opt_with_clause UPDATE relation_expr_opt_alias SET set_clause_list from_clause where_or_current_clause returning_clause') {
            throw ImplementationGap::production($form);
        }

        return new Update(
            $this->lowering->queries->with($form->node(0)),
            $this->target($form->node(2)),
            $this->assignments($form->node(4)),
            $this->lowering->queries->fromItem($form->node(5)),
            $this->where($form->node(6)),
            $this->returning($form->node(7)),
        );
    }

    /**
     * Lowers `DeleteStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function delete(Node $statement): Delete
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'DeleteStmt: opt_with_clause DELETE_P FROM relation_expr_opt_alias using_clause where_or_current_clause returning_clause') {
            throw ImplementationGap::production($form);
        }

        return new Delete(
            $this->lowering->queries->with($form->node(0)),
            $this->target($form->node(3)),
            $this->using($form->node(4)),
            $this->where($form->node(5)),
            $this->returning($form->node(6)),
        );
    }

    /**
     * Lowers `using_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function using(Node $clause): ?Relation
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'using_clause: USING from_list' => $this->lowering->queries->fromItem($form->node(1)),
            'using_clause:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `relation_expr_opt_alias`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function target(Node $target): TargetTable
    {
        $form = $this->lowering->productions->form($target);

        return match ($form->signature) {
            'relation_expr_opt_alias: relation_expr' => new TargetTable($this->lowering->queries->relation($form->node(0))),
            'relation_expr_opt_alias: relation_expr ColId' => new TargetTable($this->lowering->queries->relation($form->node(0)), $this->lowering->names->name($form->node(1))),
            'relation_expr_opt_alias: relation_expr AS ColId' => new TargetTable($this->lowering->queries->relation($form->node(0)), $this->lowering->names->name($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `set_clause_list`.
     *
     * @return list<Assignment|RowAssignment>
     *
     * @throws ImplementationGap When an item has no rule
     */
    public function assignments(Node $list): array
    {
        $assignments = [];
        foreach ($this->lowering->items($list, 'set_clause_list: set_clause', 'set_clause_list: set_clause_list , set_clause') as $item) {
            $form = $this->lowering->productions->form($item);
            $assignments[] = match ($form->signature) {
                'set_clause: set_target = a_expr' => new Assignment($this->column($form->node(0)), $this->lowering->expressions->expression($form->node(2))),
                'set_clause: ( set_target_list ) = a_expr' => new RowAssignment($this->columns($form->node(1)), $this->lowering->expressions->expression($form->node(4))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $assignments;
    }

    /**
     * Lowers `set_target_list`.
     *
     * @return list<ColumnTarget>
     */
    public function columns(Node $list): array
    {
        $columns = [];
        foreach ($this->lowering->items($list, 'set_target_list: set_target', 'set_target_list: set_target_list , set_target') as $item) {
            $columns[] = $this->column($item);
        }

        return $columns;
    }

    /**
     * Lowers `set_target`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function column(Node $target): ColumnTarget
    {
        $form = $this->lowering->productions->form($target);
        if ($form->signature !== 'set_target: ColId opt_indirection') {
            throw ImplementationGap::production($form);
        }

        return new ColumnTarget($this->lowering->names->name($form->node(0)), $this->lowering->expressions->indirection($form->node(1)));
    }

    /**
     * Lowers `where_or_current_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function where(Node $clause): Scalar|CurrentOf|null
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'where_or_current_clause: WHERE a_expr' => $this->lowering->expressions->expression($form->node(1)),
            'where_or_current_clause: WHERE CURRENT_P OF cursor_name' => new CurrentOf($this->cursor($form->node(3))),
            'where_or_current_clause:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `returning_clause`; no clause is an empty list.
     *
     * @return list<Target>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function returning(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'returning_clause: RETURNING target_list' => $this->lowering->queries->targets($form->node(1)),
            'returning_clause:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `cursor_name`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function cursor(Node $name): Name
    {
        $form = $this->lowering->productions->form($name);
        if ($form->signature !== 'cursor_name: name') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->names->name($form->node(0));
    }
}
