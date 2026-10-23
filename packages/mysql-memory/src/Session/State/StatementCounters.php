<?php

declare(strict_types=1);

namespace MySqlMemory\Session\State;

use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Statement;

/**
 * Counts client requests separately from statements executed inside stored programs.
 *
 * Parsing failures still count as Questions and Queries. Command counters start after early
 * parsing checks, before execution can fail. SQL EXECUTE counts one request and both command
 * kinds. Local program assignments count queries but not Com_set_option.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-status-variables.html.
 *
 * @visibility MySqlMemory
 */
final class StatementCounters
{
    /**
     * Records a client request, optionally excluded from Questions by the wire protocol.
     */
    public static function received(Session $session, bool $question = true): void
    {
        self::query($session);
        if ($question) {
            self::command($session, 'Questions');
        }
    }

    /**
     * Records a query, including a stored program instruction.
     */
    public static function query(Session $session): void
    {
        $session->instance->registry->status->add('Queries');
    }

    /**
     * Records the command kind once early parsing checks have succeeded.
     */
    public static function evaluated(Statement $statement, Session $session): void
    {
        if ($statement instanceof SetVariables && $session->program !== null && (new \MySqlMemory\Program\Interpreter($session, $session->program))->local($statement)) {
            return;
        }
        $name = StatementKind::of($statement);
        if ($name !== null) {
            self::command($session, $name);
        }
    }

    /**
     * Records one named counter in both scopes.
     */
    public static function command(Session $session, string $name): void
    {
        $session->instance->registry->status->add($name, $session->id);
    }
}
