<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Characteristic;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;

/**
 * The COMMENT characteristic of a stored routine.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility public
 * @example Reading the comment of a routine
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("ALTER FUNCTION f COMMENT 'adds'");
 *     $alter->characteristics[0]->text->value // => 'adds'
 */
final class RoutineComment implements Characteristic
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Text $text The comment, written as a quoted string
     */
    public function __construct(public readonly Text $text, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
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
