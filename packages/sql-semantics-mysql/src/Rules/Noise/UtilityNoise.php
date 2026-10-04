<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of SET, SHOW, EXPLAIN and utility statements.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class UtilityNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `opt_storage` is the leaf family's (LeafNoise); this family declares
     *   no noise of its own: FULL, EXTENDED, the scopes and every SHOW
     *   keyword change the request.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * - `option_type: LOCAL_SYM`, `opt_var_type: LOCAL_SYM`: "LOCAL and
     *   @@LOCAL. are synonyms for SESSION and @@SESSION."
     *   (https://dev.mysql.com/doc/refman/8.4/en/set-variable.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/show-variables.html).
     * - `internal_variable_name: DEFAULT . ident`: `DEFAULT.name` names the
     *   instance `default` of a structured variable, the same as
     *   `` `default`.name ``
     *   (https://dev.mysql.com/doc/refman/5.7/en/structured-system-variables.html).
     * - `master_or_binary: MASTER_SYM`, `master_or_binary: BINARY`,
     *   `master_or_binary: BINARY_SYM`: "SHOW MASTER LOGS is equivalent to
     *   SHOW BINARY LOGS" and "The BINARY and MASTER keywords are synonyms"
     *   (https://dev.mysql.com/doc/refman/8.0/en/show-binary-logs.html,
     *   https://dev.mysql.com/doc/refman/8.0/en/purge-binary-logs.html).
     * - `from_or_in: IN_SYM`: "FROM and IN are interchangeable" in the
     *   database and table clauses of SHOW
     *   (https://dev.mysql.com/doc/refman/8.4/en/show-tables.html,
     *   https://dev.mysql.com/doc/refman/8.4/en/show-columns.html).
     * - `describe_command: DESC`: "The DESCRIBE and EXPLAIN statements are
     *   synonyms"; DESC is the same keyword
     *   (https://dev.mysql.com/doc/refman/8.4/en/explain.html; the lexer
     *   already reads EXPLAIN as DESCRIBE, sql/lex.h).
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [
            'option_type: LOCAL_SYM' => [0 => 'SESSION_SYM'],
            'opt_var_type: LOCAL_SYM' => [0 => 'SESSION_SYM'],
            'internal_variable_name: DEFAULT . ident' => [0 => 'name:default'],
            'master_or_binary: MASTER_SYM' => [0 => 'BINARY_SYM'],
            'master_or_binary: BINARY' => [0 => 'BINARY_SYM'],
            'master_or_binary: BINARY_SYM' => [0 => 'BINARY_SYM'],
            'from_or_in: IN_SYM' => [0 => 'FROM'],
            'describe_command: DESC' => [0 => 'DESCRIBE'],
        ];
    }
}
