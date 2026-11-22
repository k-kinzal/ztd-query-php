<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * The body of a stored routine written in an external language: `AS` and the code as a string (MySQL 8.1 and later).
 *
 * The code is opaque text to the server's SQL layer; nothing is derived
 * from it.
 * Source: https://dev.mysql.com/doc/refman/9.1/en/create-procedure.html.
 *
 * @visibility SqlSemantics
 */
final class ExternalBody implements Node
{
    use Snapshot;

    /**
     * @param DollarQuotedText|Text $code The code, written as a dollar-quoted or a quoted string
     */
    public function __construct(public readonly DollarQuotedText|Text $code)
    {
        Check::input($code instanceof DollarQuotedText || $code->radix === null, 'The code of an external body is written as a quoted string.');
    }

    /**
     * Writes AS and the code.
     */
    public function render(Output $out): void
    {
        $out->keyword('AS')->node($this->code);
    }
}
