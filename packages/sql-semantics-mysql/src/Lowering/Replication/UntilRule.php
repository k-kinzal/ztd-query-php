<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

/**
 * Lowers the UNTIL clause of START SLAVE and START REPLICA.
 *
 * Rule: MYSQL-REPLICA-UNTIL-002. Scope: slave_until, slave_until_opts (5.6,
 * 5.7), opt_replica_until, replica_until (8.0 and later). The list is
 * left-recursive: its first item is a log position, a GTID condition or
 * SQL_AFTER_MTS_GAPS, every later item a log position, lowered by
 * MYSQL-SOURCE-OPTION-001 in the vocabulary of the release. Constructs:
 * ReplicaUntil. Terminates: the list spine is walked iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-replica.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Replication
 */
final class UntilRule
{
    /**
     * The productions that continue the list with a log position.
     */
    private const CONTINUED = ['slave_until_opts: slave_until_opts , master_file_def' => true, 'replica_until: replica_until , source_file_def' => true];

    /**
     * The productions that start the list with a log position.
     */
    private const POSITIONS = ['slave_until_opts: master_file_def' => true, 'replica_until: source_file_def' => true];

    /**
     * The productions that start the list with another condition, by the condition.
     */
    private const POINTS = [
        'slave_until_opts: SQL_BEFORE_GTIDS EQ TEXT_STRING_sys' => UntilPoint::BeforeGtids, 'slave_until_opts: SQL_AFTER_GTIDS EQ TEXT_STRING_sys' => UntilPoint::AfterGtids,
        'slave_until_opts: SQL_AFTER_MTS_GAPS' => UntilPoint::AfterGaps, 'replica_until: SQL_BEFORE_GTIDS EQ TEXT_STRING_sys' => UntilPoint::BeforeGtids,
        'replica_until: SQL_AFTER_GTIDS EQ TEXT_STRING_sys' => UntilPoint::AfterGtids, 'replica_until: SQL_AFTER_MTS_GAPS' => UntilPoint::AfterGaps,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an optional UNTIL clause; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function until(Node $clause, Terminology $terminology): ?ReplicaUntil
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'slave_until:', 'opt_replica_until:' => null,
            'slave_until: UNTIL_SYM slave_until_opts', 'opt_replica_until: UNTIL_SYM replica_until' => $this->conditions($form->node(1), $terminology),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the condition list.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function conditions(Node $list, Terminology $terminology): ReplicaUntil
    {
        $rule = new SourceOptionRule($this->lowering);
        $positions = [];
        $form = $this->lowering->form($list);
        while (isset(self::CONTINUED[$form->signature])) {
            array_unshift($positions, $rule->option($form->node(2), $terminology));
            $form = $this->lowering->form($form->node(0));
        }
        if (isset(self::POSITIONS[$form->signature])) {
            return new ReplicaUntil(null, null, [$rule->option($form->node(0), $terminology), ...$positions]);
        }
        $point = self::POINTS[$form->signature] ?? throw ImplementationGap::production($form);

        return new ReplicaUntil($point, $point === UntilPoint::AfterGaps ? null : $this->lowering->literals->text($form->node(2)), $positions);
    }
}
