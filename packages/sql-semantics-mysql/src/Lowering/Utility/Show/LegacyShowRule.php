<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Show;

use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinaryLogStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicas;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrorCount;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfiles;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarningCount;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the SHOW statements of MySQL 5.6 and 5.7: `show: SHOW show_param`.
 *
 * Rule: MYSQL-SHOW-LEGACY-LOWERING-001. Scope: show, show_param,
 * show_engine_param. Each show_param production is the same command as
 * the 8.0 rule of the same words and lowers into the same class; the
 * MySQL 5.6 and 5.7 spellings of a production differ only in the clause
 * rules they use (wild_and_where or opt_wild_or_where, opt_limit_clause_init
 * or opt_limit_clause, where_clause or opt_where_clause), which
 * MYSQL-SHOW-CLAUSE-LOWERING-001 lowers alike. The statements about schema
 * objects and the server are lowered by
 * MYSQL-SHOW-LEGACY-OBJECT-LOWERING-001. Terminates: every part is a strict
 * subtree. Source: https://dev.mysql.com/doc/refman/5.7/en/show.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class LegacyShowRule
{
    /**
     * The productions of the diagnostics, profiling and replication statements, by command.
     */
    private const KINDS = [
        'show_param: COUNT_SYM ( * ) WARNINGS' => 'warningCount', 'show_param: COUNT_SYM ( * ) ERRORS' => 'errorCount',
        'show_param: WARNINGS opt_limit_clause_init' => 'warnings', 'show_param: WARNINGS opt_limit_clause' => 'warnings',
        'show_param: ERRORS opt_limit_clause_init' => 'errors', 'show_param: ERRORS opt_limit_clause' => 'errors',
        'show_param: PROFILES_SYM' => 'profiles', 'show_param: PROFILE_SYM opt_profile_defs opt_profile_args opt_limit_clause_init' => 'profile',
        'show_param: PROFILE_SYM opt_profile_defs opt_profile_args opt_limit_clause' => 'profile',
        'show_param: master_or_binary LOGS_SYM' => 'binaryLogs', 'show_param: SLAVE HOSTS_SYM' => 'replicas',
        'show_param: BINLOG_SYM EVENTS_SYM binlog_in binlog_from opt_limit_clause_init' => 'binlogEvents',
        'show_param: BINLOG_SYM EVENTS_SYM binlog_in binlog_from opt_limit_clause' => 'binlogEvents',
        'show_param: RELAYLOG_SYM EVENTS_SYM binlog_in binlog_from opt_limit_clause_init' => 'relaylogEvents',
        'show_param: RELAYLOG_SYM EVENTS_SYM binlog_in binlog_from opt_limit_clause opt_channel' => 'relaylogEvents',
        'show_param: MASTER_SYM STATUS_SYM' => 'masterStatus', 'show_param: SLAVE STATUS_SYM' => 'slaveStatus', 'show_param: SLAVE STATUS_SYM opt_channel' => 'slaveStatus',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of `show`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        if ($form->signature !== 'show: SHOW show_param') {
            throw ImplementationGap::production($form);
        }
        $param = $this->lowering->form($form->node(1));
        $kind = self::KINDS[$param->signature] ?? null;

        return $kind === null ? (new LegacyObjectRule($this->lowering))->statement($param) : $this->session($kind, $param);
    }

    /**
     * Lowers a diagnostics, profiling or replication statement of a kind.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function session(string $kind, Form $form): Statement
    {
        $clauses = new ClauseRule($this->lowering);

        return match ($kind) {
            'warningCount' => new ShowWarningCount(),
            'errorCount' => new ShowErrorCount(),
            'warnings' => new ShowWarnings($this->lowering->queries->limit($form->node(1))),
            'errors' => new ShowErrors($this->lowering->queries->limit($form->node(1))),
            'profiles' => new ShowProfiles(),
            'profile' => new ShowProfile($clauses->sections($form->node(1)), $clauses->query($form->node(2)), $this->lowering->queries->limit($form->node(3))),
            'binaryLogs' => $this->binaryLogs($form),
            'replicas' => new ShowReplicas(Terminology::Legacy),
            'binlogEvents' => new ShowBinlogEvents($clauses->file($form->node(2)), $clauses->position($form->node(3)), $this->lowering->queries->limit($form->node(4))),
            'relaylogEvents' => new ShowRelaylogEvents(
                $clauses->file($form->node(2)),
                $clauses->position($form->node(3)),
                $this->lowering->queries->limit($form->node(4)),
                count($form->node->children) === 6 ? $this->lowering->replication->channel($form->node(5)) : null,
            ),
            'masterStatus' => new ShowBinaryLogStatus(Terminology::Legacy),
            'slaveStatus' => new ShowReplicaStatus(Terminology::Legacy, count($form->node->children) === 3 ? $this->lowering->replication->channel($form->node(2)) : null),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers SHOW BINARY LOGS and SHOW MASTER LOGS.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function binaryLogs(Form $form): ShowBinaryLogs
    {
        $this->lowering->utility->binaryLogsWord($form->node(0));

        return new ShowBinaryLogs();
    }
}
