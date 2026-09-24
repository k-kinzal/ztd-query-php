<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Storage;

/**
 * Distinguishes the undo and the legacy redo log files a logfile group receives.
 * @visibility public
 * @example Naming the undo log file
 *     \SqlSemantics\Model\Definition\Storage\LogFileKind::Undo->value // => 'UNDOFILE'
 */
enum LogFileKind: string
{
    case Undo = 'UNDOFILE';
    case Redo = 'REDOFILE';
}
