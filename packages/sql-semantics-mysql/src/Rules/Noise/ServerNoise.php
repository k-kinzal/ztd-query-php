<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of server administration and storage objects.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ServerNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `opt_work: WORK_SYM`: `BEGIN [WORK]`, `COMMIT [WORK]`, `ROLLBACK
     *   [WORK]`; the word is optional and the grammar action stores nothing
     *   (https://dev.mysql.com/doc/refman/8.4/en/commit.html).
     * - `opt_savepoint: SAVEPOINT_SYM`: `ROLLBACK [WORK] TO [SAVEPOINT]
     *   identifier`; the word is optional
     *   (https://dev.mysql.com/doc/refman/8.4/en/savepoint.html).
     * - The comma at position 1 of the MySQL 5.x option list productions
     *   `tablespace_options`, `alter_tablespace_options`,
     *   `logfile_group_options`, `alter_logfile_group_options`,
     *   `change_ts_options` and `drop_ts_options`: each list has the same
     *   production with and without the comma and the same action, so the
     *   comma between two options is optional
     *   (https://dev.mysql.com/doc/refman/5.7/en/create-tablespace.html,
     *   https://dev.mysql.com/doc/refman/5.7/en/create-logfile-group.html;
     *   MySQL 8.0 writes the same choice as `opt_comma`).
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'opt_work: WORK_SYM' => [0],
            'opt_savepoint: SAVEPOINT_SYM' => [0],
            'tablespace_options: tablespace_options , tablespace_option' => [1],
            'alter_tablespace_options: alter_tablespace_options , alter_tablespace_option' => [1],
            'logfile_group_options: logfile_group_options , logfile_group_option' => [1],
            'alter_logfile_group_options: alter_logfile_group_options , alter_logfile_group_option' => [1],
            'change_ts_options: change_ts_options , change_ts_option' => [1],
            'drop_ts_options: drop_ts_options_list , drop_ts_option' => [1],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * - `begin_or_start: BEGIN_SYM`: `XA {START|BEGIN} xid`; both words start
     *   an XA transaction and the grammar action of `xa` does not tell them
     *   apart (https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html).
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [
            'begin_or_start: BEGIN_SYM' => [0 => 'START_SYM'],
        ];
    }
}
