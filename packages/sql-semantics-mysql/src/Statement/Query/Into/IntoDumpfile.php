<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Into;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `INTO DUMPFILE 'file'`: one row written without separators or escaping.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html.
 *
 * @visibility public
 * @example Reading the file name
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t INTO DUMPFILE '/tmp/a.bin'");
 *     $query->statement->into->file->value // => '/tmp/a.bin'
 */
final class IntoDumpfile implements IntoDestination
{
    use Snapshot;

    /**
     * @param Text $file The file name
     */
    public function __construct(public readonly Text $file)
    {
    }

    /**
     * Writes the destination.
     */
    public function render(Output $out): void
    {
        $out->keyword('DUMPFILE')->node($this->file);
    }
}
