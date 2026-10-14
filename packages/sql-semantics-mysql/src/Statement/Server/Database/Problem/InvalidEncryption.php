<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database\Problem;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A database encryption option whose decoded value is neither Y nor N.
 *
 * The complete operand is retained; a consumer bounds its diagnostic display.
 *
 * @visibility public
 * @example Retaining an invalid encryption operand
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('bad')))->value->value // => 'bad'
 */
final class InvalidEncryption implements Diagnostic
{
    use Snapshot;

    /**
     * @param Text $value The decoded encryption option
     */
    public function __construct(public readonly Text $value)
    {
    }

    /**
     * Describes the operand domain independently of display bounds.
     */
    public function message(): string
    {
        return "Incorrect argument (should be Y or N) value: '" . $this->value->value . "'";
    }
}
