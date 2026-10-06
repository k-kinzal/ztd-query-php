<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * An SQLSTATE value: `SQLSTATE [VALUE] 'xxxxx'`.
 *
 * The optional word VALUE carries no meaning and is not kept. A value that
 * is not five digits or upper-case letters, or that begins with `00`, is
 * still structured; the statement that holds it reports it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-condition.html.
 *
 * @visibility public
 * @example Checking the form of an SQLSTATE value
 *     $text = new \SqlSemantics\Platform\MySql\Statement\Literal\Text('00000');
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState($text))->valid() // => false
 */
final class SqlState implements Condition
{
    use Snapshot;

    /**
     * @param Text $state The five characters of the value, written as a quoted string
     */
    public function __construct(public readonly Text $state)
    {
        Check::input($state->radix === null, 'An SQLSTATE value is written as a quoted string.');
    }

    /**
     * Tells whether the value has the form of an SQLSTATE that is not a success class.
     */
    public function valid(): bool
    {
        return preg_match('/\A[0-9A-Z]{5}\z/', $this->state->value) === 1 && !str_starts_with($this->state->value, '00');
    }

    /**
     * Writes SQLSTATE and the value.
     */
    public function render(Output $out): void
    {
        $out->keyword('SQLSTATE')->node($this->state);
    }
}
