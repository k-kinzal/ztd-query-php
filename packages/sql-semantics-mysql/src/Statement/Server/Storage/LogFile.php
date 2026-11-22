<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\LogFileKind;

/**
 * A log file a log file group statement adds: `UNDOFILE 'file'` or `REDOFILE 'file'`.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-logfile-group.html.
 *
 * @visibility SqlSemantics
 * @example Holding an undo file
 *     $file = new \SqlSemantics\Platform\MySql\Statement\Server\Storage\LogFile(\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\LogFileKind::Undo, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('u.log'));
 *     $file->file->value // => 'u.log'
 */
final class LogFile implements Node
{
    use Snapshot;

    /**
     * @param LogFileKind $kind UNDOFILE or REDOFILE
     * @param Text $file The file name
     */
    public function __construct(public readonly LogFileKind $kind, public readonly Text $file)
    {
    }

    /**
     * Writes the file.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->file);
    }
}
