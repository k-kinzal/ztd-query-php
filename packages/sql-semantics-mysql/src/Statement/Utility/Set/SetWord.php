<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

/**
 * A keyword the grammar accepts as the value of a variable assignment in SET.
 *
 * DEFAULT requests the default value of the variable: the global value for
 * a session assignment, the compiled-in default for a global one. The other
 * keywords stand for the text of the word (`binary` for BINARY), which the
 * variable interprets; ROW and SYSTEM are accepted from MySQL 8.0 on (for
 * example `binlog_format = ROW`, `time_zone = SYSTEM`).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html.
 *
 * @visibility public
 * @example Reading the keyword of a word
 *     \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::System->value // => 'SYSTEM'
 */
enum SetWord: string
{
    case Default = 'DEFAULT';
    case On = 'ON';
    case All = 'ALL';
    case Binary = 'BINARY';
    case Row = 'ROW';
    case System = 'SYSTEM';
}
