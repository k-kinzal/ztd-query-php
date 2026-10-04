<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

/**
 * A size option of a tablespace or log file group.
 *
 * Each case holds its keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-logfile-group.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind::Extent->value // => 'EXTENT_SIZE'
 */
enum SizeOptionKind: string
{
    case Initial = 'INITIAL_SIZE';
    case Autoextend = 'AUTOEXTEND_SIZE';
    case Maximum = 'MAX_SIZE';
    case Extent = 'EXTENT_SIZE';
    case UndoBuffer = 'UNDO_BUFFER_SIZE';
    case RedoBuffer = 'REDO_BUFFER_SIZE';
    case FileBlock = 'FILE_BLOCK_SIZE';
}
