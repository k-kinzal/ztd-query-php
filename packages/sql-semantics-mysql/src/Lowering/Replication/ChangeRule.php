<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Statement\Statement;

/**
 * Lowers CHANGE MASTER TO, CHANGE REPLICATION SOURCE TO and CHANGE REPLICATION FILTER of every release.
 *
 * Rule: MYSQL-CHANGE-001. Scope: change (5.6 to 8.3), change_replication_stmt
 * (8.4 and later), change_replication_source, master_defs, source_defs. The
 * 5.x statement is written in the legacy vocabulary; from 8.0 the statement
 * is in the current one, CHANGE MASTER of 8.0 to 8.3 being its synonym
 * (change_replication_source: MASTER_SYM fills the same LEX command and only
 * adds a deprecation warning). The options are lowered by
 * MYSQL-SOURCE-OPTION-001, the filters by MYSQL-FILTER-001 and the channel by
 * the entry rule. Constructs: ChangeReplicationSource, ChangeReplicationFilter.
 * Terminates: the option lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html,
 * https://dev.mysql.com/doc/refman/8.0/en/change-master-to.html,
 * https://dev.mysql.com/doc/refman/8.4/en/change-replication-filter.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Replication
 */
final class ChangeRule
{
    /**
     * The CHANGE ... TO productions, by the vocabulary they are written in.
     */
    private const SOURCES = [
        'change: CHANGE MASTER_SYM TO_SYM master_defs' => Terminology::Legacy,
        'change: CHANGE MASTER_SYM TO_SYM master_defs opt_channel' => Terminology::Legacy,
        'change: CHANGE change_replication_source TO_SYM source_defs opt_channel' => Terminology::Current,
        'change_replication_stmt: CHANGE REPLICATION SOURCE_SYM TO_SYM source_defs opt_channel' => Terminology::Current,
    ];

    /**
     * The CHANGE REPLICATION FILTER productions.
     */
    private const FILTERS = [
        'change: CHANGE REPLICATION FILTER_SYM filter_defs' => true,
        'change: CHANGE REPLICATION FILTER_SYM filter_defs opt_channel' => true,
        'change_replication_stmt: CHANGE REPLICATION FILTER_SYM filter_defs opt_channel' => true,
    ];

    /**
     * The productions of the option list spines.
     */
    private const LISTS = ['master_defs: master_def', 'master_defs: master_defs , master_def', 'source_defs: source_def', 'source_defs: source_defs , source_def'];

    /**
     * The two spellings of the statement keyword in 8.0 to 8.3.
     */
    private const SPELLINGS = ['change_replication_source: MASTER_SYM', 'change_replication_source: REPLICATION SOURCE_SYM'];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one CHANGE statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        $channel = count($form->node->children) > 4 ? $this->lowering->replication->channel($form->node(count($form->node->children) - 1)) : null;
        if (isset(self::FILTERS[$form->signature])) {
            return new ChangeReplicationFilter((new FilterRule($this->lowering))->filters($form->node(3)), $channel);
        }
        $terminology = self::SOURCES[$form->signature] ?? throw ImplementationGap::production($form);
        $position = 3;
        if ($form->signature === 'change: CHANGE change_replication_source TO_SYM source_defs opt_channel') {
            $spelling = $this->lowering->form($form->node(1));
            if (!in_array($spelling->signature, self::SPELLINGS, true)) {
                throw ImplementationGap::production($spelling);
            }
        } elseif ($form->signature === 'change_replication_stmt: CHANGE REPLICATION SOURCE_SYM TO_SYM source_defs opt_channel') {
            $position = 4;
        }
        $rule = new SourceOptionRule($this->lowering);
        $options = [];
        foreach ((new Spine($this->lowering))->items($form->node($position), self::LISTS) as $item) {
            $options[] = $rule->option($item, $terminology);
        }

        return new ChangeReplicationSource($terminology, $options, $channel);
    }
}
