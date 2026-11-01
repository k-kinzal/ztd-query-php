<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation;

/**
 * Session, nondeterministic and side-effecting functions excluded from column expressions.
 *
 * A generation expression excludes every function whose result depends on more than
 * its arguments. An expression default excludes only the functions that read the
 * server's state or wait, so it accepts the clock, the session and RAND() (verified
 * on 8.0.44 and 8.4.7: `DEFAULT (now())` and `DEFAULT (rand())` are accepted,
 * `DEFAULT (row_count())` answers ER_DEFAULT_VAL_GENERATED_NAMED_FUNCTION_IS_NOT_ALLOWED).
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Functions
{
    /**
     * @param \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole $role The column expression the call belongs to
     * @return array<string, string> Called spellings mapped to canonical diagnostic names
     */
    public function names(\SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole $role = \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole::Generated): array
    {
        $always = ['found_rows' => 'found_rows', 'row_count' => 'row_count', 'last_insert_id' => 'last_insert_id', 'get_lock' => 'get_lock', 'release_lock' => 'release_lock', 'is_free_lock' => 'is_free_lock', 'is_used_lock' => 'is_used_lock', 'release_all_locks' => 'release_all_locks', 'sleep' => 'sleep', 'benchmark' => 'benchmark', 'version' => 'version()', 'load_file' => 'load_file', 'values' => 'values', 'icu_version' => 'icu_version()', 'master_pos_wait' => 'source_pos_wait', 'source_pos_wait' => 'source_pos_wait'];
        $session = ['connection_id' => 'connection_id', 'current_user' => 'current_user', 'user' => 'user', 'session_user' => 'user', 'system_user' => 'user', 'database' => 'database', 'schema' => 'database', 'now' => 'now', 'current_timestamp' => 'now', 'localtime' => 'now', 'localtimestamp' => 'now', 'curdate' => 'curdate', 'current_date' => 'curdate', 'curtime' => 'curtime', 'current_time' => 'curtime', 'utc_date' => 'utc_date', 'utc_time' => 'utc_time', 'utc_timestamp' => 'utc_timestamp', 'unix_timestamp' => 'unix_timestamp', 'rand' => 'rand', 'uuid' => 'uuid', 'uuid_short' => 'uuid_short', 'current_role' => 'current_role'];
        if ($role === \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole::Default) {
            return $always;
        }
        return [...$always, ...$session, 'sysdate' => 'sysdate', 'statement_digest' => 'statement_digest', 'random_bytes' => 'random_bytes'];
    }
}
