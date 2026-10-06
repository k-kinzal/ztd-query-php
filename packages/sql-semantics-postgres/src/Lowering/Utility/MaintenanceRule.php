<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Analyze;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Checkpoint;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Cluster;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Explain;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Lock;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\LockMode;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\MaintenanceTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionSyntax;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Vacuum;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the maintenance commands, EXPLAIN and LOCK.
 *
 * Rule: PG-MAINTENANCE-LOWER-001. Scope: `VacuumStmt`, `AnalyzeStmt`,
 * `vacuum_relation`, `vacuum_relation_list`, `opt_vacuum_relation_list`,
 * `ClusterStmt`, `cluster_index_specification`, `ReindexStmt`,
 * `reindex_target_relation`, `reindex_target_all`,
 * `opt_reindex_option_list`, `CheckPointStmt`, `ExplainStmt`,
 * `ExplainableStmt`, `LockStmt`, `opt_lock`, `lock_type`. Constructors:
 * `Vacuum`, `Analyze`, `MaintenanceTarget`, `Cluster`, `Reindex`,
 * `Checkpoint`, `Explain`, `Lock`. The options are lowered by
 * PG-UTILITY-OPTION-LOWER-001; the explained statement by the family that
 * owns it, through the hub. Termination: lists are flattened iteratively;
 * the explained statement is a proper part of the tree.
 * Source: https://www.postgresql.org/docs/17/sql-vacuum.html, https://www.postgresql.org/docs/17/sql-analyze.html,
 * https://www.postgresql.org/docs/17/sql-cluster.html, https://www.postgresql.org/docs/17/sql-reindex.html,
 * https://www.postgresql.org/docs/17/sql-checkpoint.html, https://www.postgresql.org/docs/17/sql-explain.html,
 * https://www.postgresql.org/docs/17/sql-lock.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class MaintenanceRule
{
    /**
     * The statements EXPLAIN accepts.
     */
    private const EXPLAINABLE = [
        'ExplainableStmt: SelectStmt', 'ExplainableStmt: InsertStmt', 'ExplainableStmt: UpdateStmt', 'ExplainableStmt: DeleteStmt', 'ExplainableStmt: MergeStmt',
        'ExplainableStmt: DeclareCursorStmt', 'ExplainableStmt: CreateAsStmt', 'ExplainableStmt: CreateMatViewStmt', 'ExplainableStmt: RefreshMatViewStmt', 'ExplainableStmt: ExecuteStmt',
    ];

    /**
     * The mode each `lock_type` production names.
     */
    private const MODES = [
        'lock_type: ACCESS SHARE' => LockMode::AccessShare,
        'lock_type: ROW SHARE' => LockMode::RowShare,
        'lock_type: ROW EXCLUSIVE' => LockMode::RowExclusive,
        'lock_type: SHARE UPDATE EXCLUSIVE' => LockMode::ShareUpdateExclusive,
        'lock_type: SHARE' => LockMode::Share,
        'lock_type: SHARE ROW EXCLUSIVE' => LockMode::ShareRowExclusive,
        'lock_type: EXCLUSIVE' => LockMode::Exclusive,
        'lock_type: ACCESS EXCLUSIVE' => LockMode::AccessExclusive,
    ];

    /**
     * What each REINDEX target production rebuilds.
     */
    private const REBUILT = [
        'reindex_target_relation: INDEX' => ReindexTarget::Index,
        'reindex_target_relation: TABLE' => ReindexTarget::Table,
        'reindex_target_all: SYSTEM_P' => ReindexTarget::System,
        'reindex_target_all: DATABASE' => ReindexTarget::Database,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a maintenance command, EXPLAIN or LOCK.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $options = new OptionRule($this->lowering);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'VacuumStmt: VACUUM opt_full opt_freeze opt_verbose opt_analyze opt_vacuum_relation_list' => new Vacuum($options->words($form->node(1), $form->node(2), $form->node(3), $form->node(4)), OptionSyntax::Words, $this->targets($form->node(5))),
            'VacuumStmt: VACUUM ( utility_option_list ) opt_vacuum_relation_list' => new Vacuum($options->options($form->node(2)), OptionSyntax::Parenthesized, $this->targets($form->node(4))),
            'AnalyzeStmt: analyze_keyword opt_verbose opt_vacuum_relation_list' => new Analyze($options->keyword($form->node(0)), $options->words($form->node(1)), OptionSyntax::Words, $this->targets($form->node(2))),
            'AnalyzeStmt: analyze_keyword ( utility_option_list ) opt_vacuum_relation_list' => new Analyze($options->keyword($form->node(0)), $options->options($form->node(2)), OptionSyntax::Parenthesized, $this->targets($form->node(4))),
            'ClusterStmt: CLUSTER opt_verbose qualified_name cluster_index_specification' => new Cluster($options->words($form->node(1)), OptionSyntax::Words, $names->qualified($form->node(2)), $this->index($form->node(3))),
            'ClusterStmt: CLUSTER ( utility_option_list ) qualified_name cluster_index_specification' => new Cluster($options->options($form->node(2)), OptionSyntax::Parenthesized, $names->qualified($form->node(4)), $this->index($form->node(5))),
            'ClusterStmt: CLUSTER opt_verbose' => new Cluster($options->words($form->node(1))),
            'ClusterStmt: CLUSTER opt_verbose name ON qualified_name' => new Cluster($options->words($form->node(1)), OptionSyntax::Words, $names->qualified($form->node(4)), $names->name($form->node(2)), true),
            'ClusterStmt: CLUSTER ( utility_option_list )' => new Cluster($options->options($form->node(2)), OptionSyntax::Parenthesized),
            'ReindexStmt: REINDEX opt_reindex_option_list reindex_target_relation opt_concurrently qualified_name' => new Reindex($this->rebuilt($form->node(2)), $names->qualified($form->node(4)), $this->reindexOptions($form->node(1)), $this->lowering->flags->present($form->node(3))),
            'ReindexStmt: REINDEX opt_reindex_option_list SCHEMA opt_concurrently name' => new Reindex(ReindexTarget::Schema, $names->name($form->node(4)), $this->reindexOptions($form->node(1)), $this->lowering->flags->present($form->node(3))),
            'ReindexStmt: REINDEX opt_reindex_option_list reindex_target_all opt_concurrently opt_single_name' => new Reindex($this->rebuilt($form->node(2)), $names->optional($form->node(4)), $this->reindexOptions($form->node(1)), $this->lowering->flags->present($form->node(3))),
            'CheckPointStmt: CHECKPOINT' => new Checkpoint(),
            'LockStmt: LOCK_P opt_table relation_expr_list opt_lock opt_nowait' => $this->lock($form->node(1), $form->node(2), $form->node(3), $form->node(4)),
            default => $this->explain($statement),
        };
    }

    /**
     * Lowers `ExplainStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function explain(Node $statement): Explain
    {
        $form = $this->lowering->productions->form($statement);
        $options = new OptionRule($this->lowering);

        return match ($form->signature) {
            'ExplainStmt: EXPLAIN ExplainableStmt' => new Explain($this->explained($form->node(1))),
            'ExplainStmt: EXPLAIN analyze_keyword opt_verbose ExplainableStmt' => new Explain($this->explained($form->node(3)), [$options->analyze($form->node(1)), ...$options->words($form->node(2))]),
            'ExplainStmt: EXPLAIN VERBOSE ExplainableStmt' => new Explain($this->explained($form->node(2)), [$options->word('verbose')]),
            'ExplainStmt: EXPLAIN ( utility_option_list ) ExplainableStmt' => new Explain($this->explained($form->node(4)), $options->options($form->node(2)), OptionSyntax::Parenthesized),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `ExplainableStmt` through the family that owns the statement.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function explained(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        if (!in_array($form->signature, self::EXPLAINABLE, true)) {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->statement($form->node(0));
    }

    /**
     * Lowers `opt_vacuum_relation_list`; no table is an empty list.
     *
     * @return list<MaintenanceTarget>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function targets(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'opt_vacuum_relation_list:') {
            return [];
        }
        if ($form->signature !== 'opt_vacuum_relation_list: vacuum_relation_list') {
            throw ImplementationGap::production($form);
        }
        $targets = [];
        foreach ($this->lowering->items($form->node(0), 'vacuum_relation_list: vacuum_relation', 'vacuum_relation_list: vacuum_relation_list , vacuum_relation') as $item) {
            $relation = $this->lowering->productions->form($item);
            if ($relation->signature !== 'vacuum_relation: qualified_name opt_name_list') {
                throw ImplementationGap::production($relation);
            }
            $targets[] = new MaintenanceTarget($this->lowering->names->qualified($relation->node(0)), $this->lowering->names->names($relation->node(1)));
        }

        return $targets;
    }

    /**
     * Lowers `cluster_index_specification`; no index is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function index(Node $specification): ?Name
    {
        $form = $this->lowering->productions->form($specification);

        return match ($form->signature) {
            'cluster_index_specification: USING name' => $this->lowering->names->name($form->node(1)),
            'cluster_index_specification:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `reindex_target_relation` or `reindex_target_all`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function rebuilt(Node $target): ReindexTarget
    {
        $form = $this->lowering->productions->form($target);

        return self::REBUILT[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers `opt_reindex_option_list`; no list is empty.
     *
     * @return list<\SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function reindexOptions(Node $list): array
    {
        $form = $this->lowering->productions->form($list);

        return match ($form->signature) {
            'opt_reindex_option_list: ( utility_option_list )' => (new OptionRule($this->lowering))->options($form->node(1)),
            'opt_reindex_option_list:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the parts of `LockStmt`; the optional word TABLE is noise.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function lock(Node $word, Node $relations, Node $mode, Node $nowait): Lock
    {
        $this->lowering->flags->present($word);
        $form = $this->lowering->productions->form($mode);
        $written = match ($form->signature) {
            'opt_lock: IN_P lock_type MODE' => $this->lowering->productions->form($form->node(1)),
            'opt_lock:' => null,
            default => throw ImplementationGap::production($form),
        };

        return new Lock(
            $this->lowering->queries->relations($relations),
            $written === null ? null : (self::MODES[$written->signature] ?? throw ImplementationGap::production($written)),
            $this->lowering->flags->present($nowait),
        );
    }
}
