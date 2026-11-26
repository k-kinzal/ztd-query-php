<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Into;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * `INTO DUMPFILE 'file'`: one row written without separators or escaping.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html.
 *
 * @visibility SqlSemantics
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
