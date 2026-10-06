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
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the SHOW statements of MySQL 8.0 and later: one rule per statement.
 *
 * Rule: MYSQL-SHOW-LOWERING-001. Scope: every `show_*_stmt` rule. A rule
 * lowers into the class of its command; the statements about schema
 * objects, stored programs and the server are lowered by
 * MYSQL-SHOW-OBJECT-LOWERING-001, the clauses by
 * MYSQL-SHOW-CLAUSE-LOWERING-001. SLAVE and REPLICA, and MASTER STATUS and
 * BINARY LOG STATUS, are kept as written (Terminology). SHOW PARSE_TREE
 * lowers the statement it wraps through the statement dispatcher.
 * Terminates: every part is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class ShowRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of one of the `show_*_stmt` rules.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        $clauses = new ClauseRule($this->lowering);
        switch ($form->signature) {
            case 'show_warnings_stmt: SHOW WARNINGS opt_limit_clause':
                return new ShowWarnings($this->lowering->queries->limit($form->node(2)));
            case 'show_errors_stmt: SHOW ERRORS opt_limit_clause':
                return new ShowErrors($this->lowering->queries->limit($form->node(2)));
            case 'show_count_warnings_stmt: SHOW COUNT_SYM ( * ) WARNINGS':
                return new ShowWarningCount();
            case 'show_count_errors_stmt: SHOW COUNT_SYM ( * ) ERRORS':
                return new ShowErrorCount();
            case 'show_profiles_stmt: SHOW PROFILES_SYM':
                return new ShowProfiles();
            case 'show_profile_stmt: SHOW PROFILE_SYM opt_profile_defs opt_for_query opt_limit_clause':
                return new ShowProfile($clauses->sections($form->node(2)), $clauses->query($form->node(3)), $this->lowering->queries->limit($form->node(4)));
            case 'show_parse_tree_stmt: SHOW PARSE_TREE_SYM simple_statement':
                return new ShowParseTree($this->lowering->statement($form->node(2)));
            default:
                return $this->replication($form);
        }
    }

    /**
     * Lowers the SHOW statements about binary logs and replication.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function replication(Form $form): Statement
    {
        $clauses = new ClauseRule($this->lowering);
        switch ($form->signature) {
            case 'show_binary_logs_stmt: SHOW master_or_binary LOGS_SYM':
                $this->lowering->utility->binaryLogsWord($form->node(1));

                return new ShowBinaryLogs();
            case 'show_binary_logs_stmt: SHOW BINARY_SYM LOGS_SYM':
                return new ShowBinaryLogs();
            case 'show_replicas_stmt: SHOW SLAVE HOSTS_SYM':
                return new ShowReplicas(Terminology::Legacy);
            case 'show_replicas_stmt: SHOW REPLICAS_SYM':
                return new ShowReplicas(Terminology::Current);
            case 'show_binlog_events_stmt: SHOW BINLOG_SYM EVENTS_SYM opt_binlog_in binlog_from opt_limit_clause':
                return new ShowBinlogEvents($clauses->file($form->node(3)), $clauses->position($form->node(4)), $this->lowering->queries->limit($form->node(5)));
            case 'show_relaylog_events_stmt: SHOW RELAYLOG_SYM EVENTS_SYM opt_binlog_in binlog_from opt_limit_clause opt_channel':
                return new ShowRelaylogEvents($clauses->file($form->node(3)), $clauses->position($form->node(4)), $this->lowering->queries->limit($form->node(5)), $this->lowering->replication->channel($form->node(6)));
            case 'show_replica_status_stmt: SHOW replica STATUS_SYM opt_channel':
                return new ShowReplicaStatus($this->lowering->replication->terminology($form->node(1)), $this->lowering->replication->channel($form->node(3)));
            case 'show_replica_status_stmt: SHOW REPLICA_SYM STATUS_SYM opt_channel':
                return new ShowReplicaStatus(Terminology::Current, $this->lowering->replication->channel($form->node(3)));
            case 'show_master_status_stmt: SHOW MASTER_SYM STATUS_SYM':
                return new ShowBinaryLogStatus(Terminology::Legacy);
            case 'show_binary_log_status_stmt: SHOW BINARY_SYM LOG_SYM STATUS_SYM':
                return new ShowBinaryLogStatus(Terminology::Current);
            default:
                return (new ObjectRule($this->lowering))->statement($form);
        }
    }
}
