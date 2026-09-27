<?php

declare(strict_types=1);

/**
 * The combination `sql_mode` values and the modes each includes, from
 * https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sql-mode-combo and
 * https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sql-mode-combo.
 *
 * Only the included modes that change tokenization are listed. The
 * compatibility modes named after other databases were removed in 8.0, so a
 * session of an earlier release is the only one that reports them.
 *
 * @return array<string, list<string>>
 */
return [
    'ANSI' => ['ANSI_QUOTES', 'PIPES_AS_CONCAT', 'IGNORE_SPACE'],
    'DB2' => ['ANSI_QUOTES', 'PIPES_AS_CONCAT', 'IGNORE_SPACE'],
    'MAXDB' => ['ANSI_QUOTES', 'PIPES_AS_CONCAT', 'IGNORE_SPACE'],
    'MSSQL' => ['ANSI_QUOTES', 'PIPES_AS_CONCAT', 'IGNORE_SPACE'],
    'ORACLE' => ['ANSI_QUOTES', 'PIPES_AS_CONCAT', 'IGNORE_SPACE'],
    'POSTGRESQL' => ['ANSI_QUOTES', 'PIPES_AS_CONCAT', 'IGNORE_SPACE'],
    'MYSQL323' => ['HIGH_NOT_PRECEDENCE'],
    'MYSQL40' => ['HIGH_NOT_PRECEDENCE'],
];
