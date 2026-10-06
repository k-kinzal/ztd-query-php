<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetTarget;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers RESET with its items and RESET PERSIST.
 *
 * Rule: MYSQL-RESET-002. Scope: reset, reset_options, reset_option,
 * slave_reset_options, opt_replica_reset_options, source_reset_options,
 * master_or_binary_logs_and_gtids, opt_if_exists_ident,
 * persisted_variable_ident. RESET SLAVE is legacy in 5.x and, from 8.0 to
 * 8.3, the synonym of RESET REPLICA (the parser sets the same
 * REFRESH_REPLICA flag and adds a deprecation warning); RESET MASTER is
 * legacy up to 8.1 and, in 8.2 and 8.3, the synonym of RESET BINARY LOGS AND
 * GTIDS. `DEFAULT.variable` names the component `default`. Constructs:
 * Reset, ResetReplica, ResetBinaryLogs, ResetQueryCache, ResetPersist.
 * Terminates: the item list is flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/reset.html,
 * https://dev.mysql.com/doc/refman/8.4/en/reset-persist.html,
 * https://dev.mysql.com/doc/relnotes/mysql/8.2/en/news-8-2-0.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Replication
 */
final class ResetRule
{
    /**
     * The RESET REPLICA items, by the vocabulary they are read in.
     */
    private const REPLICAS = [
        'reset_option: SLAVE slave_reset_options' => Terminology::Legacy, 'reset_option: SLAVE slave_reset_options opt_channel' => Terminology::Legacy,
        'reset_option: SLAVE opt_replica_reset_options opt_channel' => Terminology::Current,
        'reset_option: REPLICA_SYM opt_replica_reset_options opt_channel' => Terminology::Current,
    ];

    /**
     * The RESET BINARY LOGS items, by the vocabulary they are read in.
     */
    private const BINARY_LOGS = [
        'reset_option: MASTER_SYM' => Terminology::Legacy, 'reset_option: MASTER_SYM source_reset_options' => Terminology::Legacy,
        'reset_option: master_or_binary_logs_and_gtids source_reset_options' => Terminology::Current,
        'reset_option: BINARY_SYM LOGS_SYM AND_SYM GTIDS_SYM source_reset_options' => Terminology::Current,
    ];

    /**
     * Whether each optional ALL of RESET REPLICA is written.
     */
    private const ALL = ['slave_reset_options:' => false, 'slave_reset_options: ALL' => true, 'opt_replica_reset_options:' => false, 'opt_replica_reset_options: ALL' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one RESET statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        if ($form->signature === 'reset: RESET_SYM PERSIST_SYM opt_if_exists_ident') {
            return $this->persist($form->node(2));
        }
        if ($form->signature !== 'reset: RESET_SYM reset_options') {
            throw ImplementationGap::production($form);
        }
        $targets = [];
        foreach ((new Spine($this->lowering))->items($form->node(1), ['reset_options: reset_option', 'reset_options: reset_options , reset_option']) as $item) {
            $targets[] = $this->target($this->lowering->form($item));
        }

        return new Reset($targets);
    }

    /**
     * Lowers one item of RESET.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function target(Form $form): ResetTarget
    {
        if ($form->signature === 'reset_option: QUERY_SYM CACHE_SYM') {
            return new ResetQueryCache();
        }
        $replica = self::REPLICAS[$form->signature] ?? null;
        if ($replica !== null) {
            $all = $this->lowering->form($form->node(1));
            $channel = count($form->node->children) === 3 ? $this->lowering->replication->channel($form->node(2)) : null;

            return new ResetReplica($replica, self::ALL[$all->signature] ?? throw ImplementationGap::production($all), $channel);
        }
        $terminology = self::BINARY_LOGS[$form->signature] ?? throw ImplementationGap::production($form);
        if ($form->signature === 'reset_option: master_or_binary_logs_and_gtids source_reset_options') {
            $words = $this->lowering->form($form->node(0));
            if ($words->signature !== 'master_or_binary_logs_and_gtids: MASTER_SYM' && $words->signature !== 'master_or_binary_logs_and_gtids: BINARY_SYM LOGS_SYM AND_SYM GTIDS_SYM') {
                throw ImplementationGap::production($words);
            }
        }
        $children = $form->node->children;
        $last = $children[count($children) - 1];

        return new ResetBinaryLogs($terminology, $last instanceof Node ? $this->first($last) : null);
    }

    /**
     * Lowers the TO clause of RESET BINARY LOGS AND GTIDS; an absent clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function first(Node $clause): ?Numeral
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'source_reset_options:' => null,
            'source_reset_options: TO_SYM real_ulonglong_num' => $this->lowering->numbers->numeral($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers RESET PERSIST.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function persist(Node $target): ResetPersist
    {
        $form = $this->lowering->form($target);
        if ($form->signature === 'opt_if_exists_ident:') {
            return new ResetPersist();
        }
        if ($form->signature !== 'opt_if_exists_ident: if_exists persisted_variable_ident') {
            throw ImplementationGap::production($form);
        }
        $ifExists = $this->lowering->options->present($form->node(0));
        $variable = $this->lowering->form($form->node(1));
        $names = $this->lowering->names;

        return match ($variable->signature) {
            'persisted_variable_ident: ident' => new ResetPersist($ifExists, $names->identifier($variable->node(0))),
            'persisted_variable_ident: ident . ident' => new ResetPersist($ifExists, $names->identifier($variable->node(2)), $names->identifier($variable->node(0))),
            'persisted_variable_ident: DEFAULT_SYM . ident' => new ResetPersist($ifExists, $names->identifier($variable->node(2)), $this->lowering->leaves->record(new Name('default'))),
            default => throw ImplementationGap::production($variable),
        };
    }
}
