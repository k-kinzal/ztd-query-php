<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\SqlError;

/**
 * The role an expression of a table definition plays: a generated column, an expression default, or a CHECK constraint.
 *
 * Each role refuses what the server refuses in it with its own errors.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html,
 * https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html.
 *
 * @visibility MySqlMemory
 */
enum ExpressionRole
{
    case Generated;
    case Default;
    case Check;

    /**
     * Answers the error of a function the role refuses, named as the server names it.
     */
    public function function(string $owner, string $function): SqlError
    {
        return match ($this) {
            self::Generated => ConstraintError::GeneratedFunction->error($owner, $function),
            self::Default => ConstraintError::DefaultFunction->error($owner, $function),
            self::Check => ConstraintError::CheckFunction->error($owner, $function),
        };
    }

    /**
     * Answers the error of a subquery, or another construct the role refuses without naming it.
     */
    public function subquery(string $owner): SqlError
    {
        return match ($this) {
            self::Generated => ConstraintError::GeneratedSubquery->error($owner),
            self::Default => ConstraintError::DefaultSubquery->error($owner),
            self::Check => ConstraintError::CheckSubquery->error($owner),
        };
    }

    /**
     * Answers the error of a user or system variable.
     */
    public function variable(string $owner): SqlError
    {
        return match ($this) {
            self::Generated, self::Default => ConstraintError::DefaultVariable->error($owner),
            self::Check => ConstraintError::CheckVariable->error($owner),
        };
    }

    /**
     * Answers the functions the role refuses, by the lowercase name a call writes, each with the name the server reports.
     *
     * A generated column refuses every function whose result depends on more than its
     * arguments; an expression default only those that read the state of the session or the
     * server, or wait; a CHECK constraint the same as a generated column but SYSDATE(),
     * STATEMENT_DIGEST() and RANDOM_BYTES() (verified on a live 8.4 server).
     *
     * @return array<string, string>
     */
    public function refused(): array
    {
        $always = ['found_rows' => 'found_rows', 'row_count' => 'row_count', 'last_insert_id' => 'last_insert_id', 'get_lock' => 'get_lock', 'release_lock' => 'release_lock', 'is_free_lock' => 'is_free_lock', 'is_used_lock' => 'is_used_lock', 'release_all_locks' => 'release_all_locks', 'sleep' => 'sleep', 'benchmark' => 'benchmark', 'version' => 'version()', 'load_file' => 'load_file', 'values' => 'values', 'icu_version' => 'icu_version()', 'master_pos_wait' => 'source_pos_wait', 'source_pos_wait' => 'source_pos_wait'];
        $session = ['connection_id' => 'connection_id', 'current_user' => 'current_user', 'user' => 'user', 'session_user' => 'user', 'system_user' => 'user', 'database' => 'database', 'schema' => 'database', 'now' => 'now', 'current_timestamp' => 'now', 'localtime' => 'now', 'localtimestamp' => 'now', 'curdate' => 'curdate', 'current_date' => 'curdate', 'curtime' => 'curtime', 'current_time' => 'curtime', 'utc_date' => 'utc_date', 'utc_time' => 'utc_time', 'utc_timestamp' => 'utc_timestamp', 'unix_timestamp' => 'unix_timestamp', 'rand' => 'rand', 'uuid' => 'uuid', 'uuid_short' => 'uuid_short', 'current_role' => 'current_role'];

        return match ($this) {
            self::Generated => [...$always, ...$session, 'sysdate' => 'sysdate', 'statement_digest' => 'statement_digest', 'random_bytes' => 'random_bytes'],
            self::Default => $always,
            self::Check => [...$always, ...$session],
        };
    }
}
