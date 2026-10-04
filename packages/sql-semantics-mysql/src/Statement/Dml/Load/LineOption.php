<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One option of the LINES clause of a text file format: `TERMINATED BY` or `STARTING BY` a string.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility public
 * @example Reading the line terminator of INTO OUTFILE
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT 1 INTO OUTFILE 'f' LINES TERMINATED BY ';'");
 *     $query->statement->into->format->lines[0]->kind // => \SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOptionKind::Terminated
 */
final class LineOption implements Node
{
    use Snapshot;

    /**
     * @param LineOptionKind $kind What the option sets
     * @param Text $text The string it sets
     */
    public function __construct(public readonly LineOptionKind $kind, public readonly Text $text)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->keyword('BY')->node($this->text);
    }
}
