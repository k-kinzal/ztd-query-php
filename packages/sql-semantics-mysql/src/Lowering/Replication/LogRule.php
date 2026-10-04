<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\StopGroupReplication;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo;
use SqlSemantics\Statement\Statement;

/**
 * Lowers PURGE BINARY LOGS, BINLOG and START and STOP GROUP_REPLICATION.
 *
 * Rule: MYSQL-REPLICATION-LOG-001. Scope: purge, purge_options, purge_option,
 * binlog_base64_event, group_replication, group_replication_start,
 * opt_group_replication_start_options, group_replication_start_options,
 * group_replication_start_option, group_replication_user,
 * group_replication_password, group_replication_plugin_auth. The word
 * MASTER or BINARY before LOGS is confirmed by the utility family, which owns
 * master_or_binary; the two are synonyms. Constructs: PurgeLogsTo,
 * PurgeLogsBefore, BinlogEvent, StartGroupReplication, StopGroupReplication,
 * CredentialOption. Terminates: the option list is flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/purge-binary-logs.html,
 * https://dev.mysql.com/doc/refman/8.4/en/binlog.html,
 * https://dev.mysql.com/doc/refman/8.4/en/start-group-replication.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Replication
 */
final class LogRule
{
    /**
     * The group replication option productions, by the option.
     */
    private const CREDENTIALS = [
        'group_replication_user: USER EQ TEXT_STRING_sys_nonewline' => Credential::User,
        'group_replication_password: PASSWORD EQ TEXT_STRING_sys_nonewline' => Credential::Password,
        'group_replication_plugin_auth: DEFAULT_AUTH_SYM EQ TEXT_STRING_sys_nonewline' => Credential::DefaultAuth,
    ];

    /**
     * The productions that pass a group replication option on to their only child.
     */
    private const FORWARD = [
        'group_replication_start_option: group_replication_user' => true, 'group_replication_start_option: group_replication_password' => true,
        'group_replication_start_option: group_replication_plugin_auth' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers PURGE.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function purge(Form $form): Statement
    {
        if ($form->signature !== 'purge: PURGE purge_options') {
            throw ImplementationGap::production($form);
        }
        $options = $this->lowering->form($form->node(1));
        if ($options->signature === 'purge_options: master_or_binary LOGS_SYM purge_option') {
            $this->lowering->utility->binaryLogsWord($options->node(0));
        } elseif ($options->signature !== 'purge_options: BINARY_SYM LOGS_SYM purge_option') {
            throw ImplementationGap::production($options);
        }
        $option = $this->lowering->form($options->node(2));

        return match ($option->signature) {
            'purge_option: TO_SYM TEXT_STRING_sys' => new PurgeLogsTo($this->lowering->literals->text($option->node(1))),
            'purge_option: BEFORE_SYM expr' => new PurgeLogsBefore($this->lowering->expressions->expression($option->node(1))),
            default => throw ImplementationGap::production($option),
        };
    }

    /**
     * Lowers BINLOG.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function binlog(Form $form): BinlogEvent
    {
        if ($form->signature !== 'binlog_base64_event: BINLOG_SYM TEXT_STRING_sys') {
            throw ImplementationGap::production($form);
        }

        return new BinlogEvent($this->lowering->literals->text($form->node(1)));
    }

    /**
     * Lowers START or STOP GROUP_REPLICATION.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function group(Form $form): Statement
    {
        return match ($form->signature) {
            'group_replication: START_SYM GROUP_REPLICATION' => new StartGroupReplication(),
            'group_replication: STOP_SYM GROUP_REPLICATION' => new StopGroupReplication(),
            'group_replication: group_replication_start opt_group_replication_start_options' => $this->start($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers START GROUP_REPLICATION with its options.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function start(Form $form): StartGroupReplication
    {
        $start = $this->lowering->form($form->node(0));
        if ($start->signature !== 'group_replication_start: START_SYM GROUP_REPLICATION') {
            throw ImplementationGap::production($start);
        }
        $list = $this->lowering->form($form->node(1));
        if ($list->signature === 'opt_group_replication_start_options:') {
            return new StartGroupReplication();
        }
        if ($list->signature !== 'opt_group_replication_start_options: group_replication_start_options') {
            throw ImplementationGap::production($list);
        }
        $options = [];
        $spine = ['group_replication_start_options: group_replication_start_option', 'group_replication_start_options: group_replication_start_options , group_replication_start_option'];
        foreach ((new Spine($this->lowering))->items($list->node(0), $spine) as $item) {
            $options[] = $this->option($item);
        }

        return new StartGroupReplication($options);
    }

    /**
     * Lowers one option of START GROUP_REPLICATION.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function option(Node $option): CredentialOption
    {
        $form = $this->lowering->form($option);
        if (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->form($form->node(0));
        }
        $credential = self::CREDENTIALS[$form->signature] ?? throw ImplementationGap::production($form);

        return new CredentialOption($credential, $this->lowering->literals->text($form->node(2)));
    }
}
