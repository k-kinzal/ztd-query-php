<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * One option of the LINES clause of a text file format: `TERMINATED BY` or `STARTING BY` a string.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility SqlSemantics
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
