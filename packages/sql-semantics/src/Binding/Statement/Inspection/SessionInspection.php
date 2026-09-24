<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Classifies MySQL SHOW requests about session variables, diagnostics, grants, and replication.
 * @visibility SqlSemantics
 */
final class SessionInspection
{
    /**
     * Grammar rules that carry one of these SHOW forms; releases before 8.0 share a single rule.
     */
    public const FORMS = ['show_param', 'show_variables_stmt', 'show_status_stmt', 'show_warnings_stmt', 'show_errors_stmt', 'show_count_errors_stmt', 'show_count_warnings_stmt', 'show_grants_stmt', 'show_binary_logs_stmt', 'show_binlog_events_stmt', 'show_relaylog_events_stmt', 'show_master_status_stmt', 'show_binary_log_status_stmt', 'show_replica_status_stmt', 'show_replicas_stmt'];

    /**
     * Returns null for dialects without SHOW and for SHOW forms owned by other families.
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql || strtoupper($node->tokens()[0]->text ?? '') !== 'SHOW') {
            return null;
        }
        $form = Tree::outer($node, self::FORMS)[0] ?? null;
        if ($form === null) {
            return null;
        }
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), array_slice($form->tokens(), 0, 6));
        if (($words[0] ?? '') === 'SHOW') {
            array_shift($words);
        }
        $request = new ShowRequest($form, $words, false, false);
        return Session\SessionReports::bind($origin, $request, $context) ?? Session\ReplicationReports::bind($origin, $request, $context);
    }
}
