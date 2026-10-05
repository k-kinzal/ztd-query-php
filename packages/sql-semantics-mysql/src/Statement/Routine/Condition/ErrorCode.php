<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A MySQL error code as a condition value.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-condition.html.
 *
 * @visibility public
 * @example Holding an error code
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1051')))->code->text // => '1051'
 */
final class ErrorCode implements Condition
{
    use Snapshot;

    /**
     * @param Numeral $code The error code
     */
    public function __construct(public readonly Numeral $code)
    {
    }

    /**
     * Writes the code.
     */
    public function render(Output $out): void
    {
        $out->node($this->code);
    }
}
