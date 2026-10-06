<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Statement\Statement;

/**
 * Lowers START SLAVE, START REPLICA, STOP SLAVE and STOP REPLICA of every release.
 *
 * Rule: MYSQL-REPLICA-001. Scope: slave, slave_start, start_slave_opts,
 * slave_connection_opts, slave_user_name_opt, slave_user_pass_opt,
 * slave_plugin_auth_opt, slave_plugin_dir_opt (5.6, 5.7), start_replica_stmt,
 * stop_replica_stmt, replica, opt_user_option, opt_password_option,
 * opt_default_auth_option, opt_plugin_dir_option (8.0 and later), and the
 * thread lists opt_slave_thread_option_list, slave_thread_option_list,
 * slave_thread_option, opt_replica_thread_option_list,
 * replica_thread_option_list, replica_thread_option. The spelling SLAVE or
 * REPLICA is kept; the UNTIL clause is lowered by MYSQL-REPLICA-UNTIL-002.
 * Constructs: StartReplica, StopReplica. Terminates: the thread lists are
 * flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-replica.html,
 * https://dev.mysql.com/doc/refman/8.4/en/stop-replica.html,
 * https://dev.mysql.com/doc/refman/5.7/en/start-slave.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Replication
 */
final class ReplicaRule
{
    /**
     * The thread keyword productions.
     */
    private const THREADS = [
        'slave_thread_option: SQL_THREAD' => ReplicaThread::Applier, 'slave_thread_option: RELAY_THREAD' => ReplicaThread::Receiver,
        'replica_thread_option: SQL_THREAD' => ReplicaThread::Applier, 'replica_thread_option: RELAY_THREAD' => ReplicaThread::Receiver,
    ];

    /**
     * The thread list spine productions, by the optional list production that holds them.
     */
    private const THREAD_LISTS = [
        'opt_slave_thread_option_list: slave_thread_option_list' => ['slave_thread_option_list: slave_thread_option', 'slave_thread_option_list: slave_thread_option_list , slave_thread_option'],
        'opt_replica_thread_option_list: replica_thread_option_list' => ['replica_thread_option_list: replica_thread_option', 'replica_thread_option_list: replica_thread_option_list , replica_thread_option'],
    ];

    /**
     * The productions of the connection options, absent or written, by the option.
     */
    private const CONNECTION = [
        'slave_user_name_opt:' => null, 'slave_user_name_opt: USER EQ TEXT_STRING_sys' => 'user', 'slave_user_pass_opt:' => null,
        'slave_user_pass_opt: PASSWORD EQ TEXT_STRING_sys' => 'password', 'slave_plugin_auth_opt:' => null,
        'slave_plugin_auth_opt: DEFAULT_AUTH_SYM EQ TEXT_STRING_sys' => 'auth', 'slave_plugin_dir_opt:' => null,
        'slave_plugin_dir_opt: PLUGIN_DIR_SYM EQ TEXT_STRING_sys' => 'dir', 'opt_user_option:' => null, 'opt_user_option: USER EQ TEXT_STRING_sys' => 'user',
        'opt_password_option:' => null, 'opt_password_option: PASSWORD EQ TEXT_STRING_sys' => 'password', 'opt_default_auth_option:' => null,
        'opt_default_auth_option: DEFAULT_AUTH_SYM EQ TEXT_STRING_sys' => 'auth', 'opt_plugin_dir_option:' => null,
        'opt_plugin_dir_option: PLUGIN_DIR_SYM EQ TEXT_STRING_sys' => 'dir',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one START or STOP statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        return match ($form->signature) {
            'slave: START_SYM SLAVE opt_slave_thread_option_list slave_until slave_connection_opts' => $this->start(
                Terminology::Legacy,
                Terminology::Legacy,
                [$form->node(2), $form->node(3), ...$this->connection($form->node(4))],
            ),
            'slave: slave_start start_slave_opts' => $this->legacyStart($form),
            'slave: STOP_SYM SLAVE opt_slave_thread_option_list' => new StopReplica(Terminology::Legacy, $this->threads($form->node(2))),
            'slave: STOP_SYM SLAVE opt_slave_thread_option_list opt_channel' => new StopReplica(
                Terminology::Legacy,
                $this->threads($form->node(2)),
                $this->lowering->replication->channel($form->node(3)),
            ),
            'start_replica_stmt: START_SYM replica opt_replica_thread_option_list opt_replica_until opt_user_option opt_password_option opt_default_auth_option opt_plugin_dir_option opt_channel' => $this->start(
                $this->lowering->replication->terminology($form->node(1)),
                Terminology::Current,
                array_slice($form->node->children, 2),
            ),
            'start_replica_stmt: START_SYM REPLICA_SYM opt_replica_thread_option_list opt_replica_until opt_user_option opt_password_option opt_default_auth_option opt_plugin_dir_option opt_channel' => $this->start(
                Terminology::Current,
                Terminology::Current,
                array_slice($form->node->children, 2),
            ),
            'stop_replica_stmt: STOP_SYM replica opt_replica_thread_option_list opt_channel' => new StopReplica(
                $this->lowering->replication->terminology($form->node(1)),
                $this->threads($form->node(2)),
                $this->lowering->replication->channel($form->node(3)),
            ),
            'stop_replica_stmt: STOP_SYM REPLICA_SYM opt_replica_thread_option_list opt_channel' => new StopReplica(
                Terminology::Current,
                $this->threads($form->node(2)),
                $this->lowering->replication->channel($form->node(3)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the START SLAVE of 5.7, whose parts are split over slave_start and start_slave_opts.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function legacyStart(Form $form): StartReplica
    {
        $start = $this->lowering->form($form->node(0));
        $options = $this->lowering->form($form->node(1));
        if ($start->signature !== 'slave_start: START_SYM SLAVE opt_slave_thread_option_list') {
            throw ImplementationGap::production($start);
        }
        if ($options->signature !== 'start_slave_opts: slave_until slave_connection_opts opt_channel') {
            throw ImplementationGap::production($options);
        }

        return $this->start(Terminology::Legacy, Terminology::Legacy, [$start->node(2), $options->node(0), ...$this->connection($options->node(1)), $options->node(2)]);
    }

    /**
     * Lowers the parts of a START statement: threads, UNTIL, the four connection options and, from 5.7, the channel.
     *
     * @param list<Node|Token> $parts
     * @throws ImplementationGap When a production has no rule
     */
    public function start(Terminology $terminology, Terminology $positions, array $parts): StartReplica
    {
        $options = [];
        foreach ([2, 3, 4, 5] as $position) {
            $options[] = $this->option($this->node($parts[$position] ?? null));
        }
        $channel = isset($parts[6]) ? $this->lowering->replication->channel($this->node($parts[6])) : null;

        return new StartReplica(
            $terminology,
            $this->threads($this->node($parts[0] ?? null)),
            (new UntilRule($this->lowering))->until($this->node($parts[1] ?? null), $positions),
            $options[0],
            $options[1],
            $options[2],
            $options[3],
            $channel,
        );
    }

    /**
     * Answers the four connection option nodes of the 5.x rule slave_connection_opts.
     *
     * @return list<Node>
     * @throws ImplementationGap When the production has no rule
     */
    public function connection(Node $options): array
    {
        $form = $this->lowering->form($options);
        if ($form->signature !== 'slave_connection_opts: slave_user_name_opt slave_user_pass_opt slave_plugin_auth_opt slave_plugin_dir_opt') {
            throw ImplementationGap::production($form);
        }

        return [$form->node(0), $form->node(1), $form->node(2), $form->node(3)];
    }

    /**
     * Lowers one connection option; an absent option is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function option(Node $option): ?Text
    {
        $form = $this->lowering->form($option);
        if (!array_key_exists($form->signature, self::CONNECTION)) {
            throw ImplementationGap::production($form);
        }

        return self::CONNECTION[$form->signature] === null ? null : $this->lowering->literals->text($form->node(2));
    }

    /**
     * Lowers a thread list; an absent list is empty.
     *
     * @return list<ReplicaThread>
     * @throws ImplementationGap When a production has no rule
     */
    public function threads(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'opt_slave_thread_option_list:' || $form->signature === 'opt_replica_thread_option_list:') {
            return [];
        }
        $spine = self::THREAD_LISTS[$form->signature] ?? throw ImplementationGap::production($form);
        $threads = [];
        foreach ((new Spine($this->lowering))->items($form->node(0), $spine) as $item) {
            $thread = $this->lowering->form($item);
            $threads[] = self::THREADS[$thread->signature] ?? throw ImplementationGap::production($thread);
        }

        return $threads;
    }

    /**
     * Narrows a statement part to the nonterminal the grammar places there.
     *
     * @throws ImplementationGap When the part is not a nonterminal
     */
    public function node(Node|Token|null $part): Node
    {
        return $part instanceof Node ? $part : throw ImplementationGap::rule('MySQL replication family: a START statement part that is not a nonterminal');
    }
}
