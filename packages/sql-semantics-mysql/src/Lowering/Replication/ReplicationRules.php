<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the replication family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-REPLICATION-ENTRY-001. Scope: the statement rules change,
 * change_replication_stmt, slave, start_replica_stmt, stop_replica_stmt,
 * reset, purge, binlog_base64_event and group_replication, handed to the
 * rule of their statement, and opt_channel and replica, which other families
 * read too. A channel is the name string of FOR CHANNEL. The keyword SLAVE or
 * REPLICA of the replica rule is answered as written: SHOW SLAVE STATUS and
 * SHOW REPLICA STATUS name their result columns after it. Terminates: one
 * dispatch per node.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-replication-statements.html,
 * https://dev.mysql.com/doc/refman/8.4/en/replication-channels.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReplicationRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a replication statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->form($statement);

        return match ($statement->name) {
            'change', 'change_replication_stmt' => (new ChangeRule($this->lowering))->statement($form),
            'slave', 'start_replica_stmt', 'stop_replica_stmt' => (new ReplicaRule($this->lowering))->statement($form),
            'reset' => (new ResetRule($this->lowering))->statement($form),
            'purge' => (new LogRule($this->lowering))->purge($form),
            'binlog_base64_event' => (new LogRule($this->lowering))->binlog($form),
            'group_replication' => (new LogRule($this->lowering))->group($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a FOR CHANNEL clause: a node of `opt_channel`; an absent clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function channel(Node $channel): ?Text
    {
        $form = $this->lowering->form($channel);

        return match ($form->signature) {
            'opt_channel:' => null,
            'opt_channel: FOR_SYM CHANNEL_SYM TEXT_STRING_sys_nonewline' => $this->lowering->literals->text($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Answers the vocabulary of the keyword SLAVE or REPLICA as written: a node of `replica`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function terminology(Node $replica): Terminology
    {
        $form = $this->lowering->form($replica);

        return match ($form->signature) {
            'replica: SLAVE' => Terminology::Legacy,
            'replica: REPLICA_SYM' => Terminology::Current,
            default => throw ImplementationGap::production($form),
        };
    }
}
