<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules;

/**
 * The noise token positions of the productions of definition and administration commands.
 *
 * Each entry names a production and the positions of its noise tokens, with
 * the reason. Nothing else may be skipped by the token correspondence check.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DefinitionNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `cmd: COMMIT|END trans_opt`: "END TRANSACTION is an alias for COMMIT"; the
     *   command is written as COMMIT (https://sqlite.org/lang_transaction.html).
     * - `trans_opt: TRANSACTION`: the keyword after BEGIN, COMMIT, END and ROLLBACK is
     *   optional (https://sqlite.org/lang_transaction.html). When a name follows it,
     *   the keyword is required to write the name and is not noise.
     * - `savepoint_opt: SAVEPOINT`: the keyword before the savepoint name of RELEASE
     *   and ROLLBACK TO is optional (https://sqlite.org/lang_savepoint.html).
     * - `cmd: PRAGMA nm dbnm EQ ...` and `cmd: PRAGMA nm dbnm LP ... RP`: "The argument
     *   may be either in parentheses or it may be separated from the pragma name by
     *   an equal sign. The two syntaxes yield identical results." The value is written
     *   after `=`; the value itself is never noise (https://sqlite.org/pragma.html).
     * - `database_kw_opt: DATABASE`: the keyword after ATTACH and DETACH is optional
     *   (https://sqlite.org/lang_attach.html, https://sqlite.org/lang_detach.html).
     * - `kwcolumn_opt: COLUMNKW`: the COLUMN keyword of ALTER TABLE ADD, DROP and
     *   RENAME is optional (https://sqlite.org/lang_altertable.html).
     * - `ccons: GENERATED ALWAYS AS generated`: "The GENERATED ALWAYS keywords at the
     *   beginning of the constraint and the VIRTUAL or STORED keyword at the end are
     *   all optional"; the two keywords are not written, the word at the end is kept
     *   (https://sqlite.org/gencol.html).
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'cmd: COMMIT|END trans_opt' => [0],
            'trans_opt: TRANSACTION' => [0],
            'savepoint_opt: SAVEPOINT' => [0],
            'cmd: PRAGMA nm dbnm EQ nmnum' => [3],
            'cmd: PRAGMA nm dbnm LP nmnum RP' => [3, 5],
            'cmd: PRAGMA nm dbnm EQ minus_num' => [3],
            'cmd: PRAGMA nm dbnm LP minus_num RP' => [3, 5],
            'database_kw_opt: DATABASE' => [0],
            'kwcolumn_opt: COLUMNKW' => [0],
            'ccons: GENERATED ALWAYS AS generated' => [0, 1],
        ];
    }
}
