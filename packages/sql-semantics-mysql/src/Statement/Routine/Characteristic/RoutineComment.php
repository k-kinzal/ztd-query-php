<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Characteristic;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * The COMMENT characteristic of a stored routine.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility SqlSemantics
 */
final class RoutineComment implements Characteristic
{
    use Snapshot;

    /**
     * @param Text $text The comment, written as a quoted string
     */
    public function __construct(public readonly Text $text)
    {
        Check::input($text->radix === null, 'A comment is written as a quoted string.');
    }

    /**
     * Writes COMMENT and the text.
     */
    public function render(Output $out): void
    {
        $out->keyword('COMMENT')->node($this->text);
    }
}
