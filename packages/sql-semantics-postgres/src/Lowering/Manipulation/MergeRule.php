<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Manipulation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeDelete;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeInsert;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeInsertDefaults;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeNothing;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeUpdate;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeWhen;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers MERGE.
 *
 * Rule: PG-MERGE-LOWER-001. Scope: `MergeStmt`, `merge_when_list`,
 * `merge_when_clause`, `merge_when_tgt_matched`,
 * `merge_when_tgt_not_matched`, `opt_merge_when_condition`, `merge_update`,
 * `merge_delete`, `merge_insert`, `merge_values_clause`. Constructors:
 * `Merge`, `MergeWhen` with `MergeMatch`, `MergeUpdate`, `MergeDelete`,
 * `MergeInsert`, `MergeInsertDefaults`, `MergeNothing`. PostgreSQL 16
 * writes the kind of a clause inside `merge_when_clause`; PostgreSQL 17
 * adds NOT MATCHED BY SOURCE, the optional BY TARGET and RETURNING.
 * Termination: the clause list is flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html, https://www.postgresql.org/docs/16/sql-merge.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class MergeRule
{
    /**
     * The kind and the positions of the condition and the action of each `merge_when_clause` production, the kind null when a nonterminal at position 0 writes it.
     */
    private const CLAUSES = [
        'merge_when_clause: WHEN MATCHED opt_merge_when_condition THEN merge_update' => [MergeMatch::Matched, 2, 4],
        'merge_when_clause: WHEN MATCHED opt_merge_when_condition THEN merge_delete' => [MergeMatch::Matched, 2, 4],
        'merge_when_clause: WHEN NOT MATCHED opt_merge_when_condition THEN merge_insert' => [MergeMatch::NotMatched, 3, 5],
        'merge_when_clause: WHEN MATCHED opt_merge_when_condition THEN DO NOTHING' => [MergeMatch::Matched, 2, null],
        'merge_when_clause: WHEN NOT MATCHED opt_merge_when_condition THEN DO NOTHING' => [MergeMatch::NotMatched, 3, null],
        'merge_when_clause: merge_when_tgt_matched opt_merge_when_condition THEN merge_update' => [null, 1, 3],
        'merge_when_clause: merge_when_tgt_matched opt_merge_when_condition THEN merge_delete' => [null, 1, 3],
        'merge_when_clause: merge_when_tgt_not_matched opt_merge_when_condition THEN merge_insert' => [null, 1, 3],
        'merge_when_clause: merge_when_tgt_matched opt_merge_when_condition THEN DO NOTHING' => [null, 1, null],
        'merge_when_clause: merge_when_tgt_not_matched opt_merge_when_condition THEN DO NOTHING' => [null, 1, null],
    ];

    /**
     * The kind of each `merge_when_tgt_matched` and `merge_when_tgt_not_matched` production.
     */
    private const KINDS = [
        'merge_when_tgt_matched: WHEN MATCHED' => MergeMatch::Matched,
        'merge_when_tgt_matched: WHEN NOT MATCHED BY SOURCE' => MergeMatch::NotMatchedBySource,
        'merge_when_tgt_not_matched: WHEN NOT MATCHED' => MergeMatch::NotMatched,
        'merge_when_tgt_not_matched: WHEN NOT MATCHED BY TARGET' => MergeMatch::NotMatched,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `MergeStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function merge(Node $statement): Merge
    {
        $form = $this->lowering->productions->form($statement);
        $returning = match ($form->signature) {
            'MergeStmt: opt_with_clause MERGE INTO relation_expr_opt_alias USING table_ref ON a_expr merge_when_list' => [],
            'MergeStmt: opt_with_clause MERGE INTO relation_expr_opt_alias USING table_ref ON a_expr merge_when_list returning_clause' => null,
            default => throw ImplementationGap::production($form),
        };
        $change = new ChangeRule($this->lowering);
        $with = $this->lowering->queries->with($form->node(0));
        $target = $change->target($form->node(3));
        $source = $this->lowering->queries->tableReference($form->node(5));
        $condition = $this->lowering->expressions->expression($form->node(7));
        $clauses = [];
        foreach ($this->lowering->items($form->node(8), 'merge_when_list: merge_when_clause', 'merge_when_list: merge_when_list merge_when_clause') as $clause) {
            $clauses[] = $this->clause($clause);
        }

        return new Merge($with, $target, $source, $condition, $clauses, $returning ?? $change->returning($form->node(9)));
    }

    /**
     * Lowers `merge_when_clause`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function clause(Node $clause): MergeWhen
    {
        $form = $this->lowering->productions->form($clause);
        $shape = self::CLAUSES[$form->signature] ?? throw ImplementationGap::production($form);
        [$kind, $condition, $action] = $shape;
        if ($kind === null) {
            $written = $this->lowering->productions->form($form->node(0));
            $kind = self::KINDS[$written->signature] ?? throw ImplementationGap::production($written);
        }

        return new MergeWhen($kind, $this->condition($form->node($condition)), $action === null ? new MergeNothing() : $this->action($form->node($action)));
    }

    /**
     * Lowers `opt_merge_when_condition`; no condition is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function condition(Node $condition): ?Scalar
    {
        $form = $this->lowering->productions->form($condition);

        return match ($form->signature) {
            'opt_merge_when_condition: AND a_expr' => $this->lowering->expressions->expression($form->node(1)),
            'opt_merge_when_condition:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `merge_update`, `merge_delete` or `merge_insert`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function action(Node $action): MergeUpdate|MergeDelete|MergeInsert|MergeInsertDefaults
    {
        $form = $this->lowering->productions->form($action);
        $insert = new InsertRule($this->lowering);

        return match ($form->signature) {
            'merge_update: UPDATE SET set_clause_list' => new MergeUpdate((new ChangeRule($this->lowering))->assignments($form->node(2))),
            'merge_delete: DELETE_P' => new MergeDelete(),
            'merge_insert: INSERT merge_values_clause' => new MergeInsert([], null, $this->values($form->node(1))),
            'merge_insert: INSERT OVERRIDING override_kind VALUE_P merge_values_clause' => new MergeInsert([], $insert->overriding($form->node(2)), $this->values($form->node(4))),
            'merge_insert: INSERT ( insert_column_list ) merge_values_clause' => new MergeInsert($insert->columns($form->node(2)), null, $this->values($form->node(4))),
            'merge_insert: INSERT ( insert_column_list ) OVERRIDING override_kind VALUE_P merge_values_clause' => new MergeInsert(
                $insert->columns($form->node(2)),
                $insert->overriding($form->node(5)),
                $this->values($form->node(7)),
            ),
            'merge_insert: INSERT DEFAULT VALUES' => new MergeInsertDefaults(),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `merge_values_clause`.
     *
     * @return list<Scalar>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function values(Node $values): array
    {
        $form = $this->lowering->productions->form($values);
        if ($form->signature !== 'merge_values_clause: VALUES ( expr_list )') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->expressions->expressions($form->node(2));
    }
}
