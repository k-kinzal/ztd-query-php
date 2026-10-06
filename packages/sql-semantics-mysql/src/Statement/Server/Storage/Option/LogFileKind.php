<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

/**
 * The kind of a log file of a log file group: UNDOFILE or REDOFILE.
 *
 * REDOFILE is accepted by the MySQL 5.x grammar only, and NDB rejects it.
 * Each case holds its keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-logfile-group.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\LogFileKind::Undo->value // => 'UNDOFILE'
 */
enum LogFileKind: string
{
    case Undo = 'UNDOFILE';
    case Redo = 'REDOFILE';
}
