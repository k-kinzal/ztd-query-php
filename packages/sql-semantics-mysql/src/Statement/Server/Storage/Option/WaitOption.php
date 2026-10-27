<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Rendering\Output;

/**
 * `WAIT` or `NO_WAIT`: whether the statement waits for the change to complete.
 *
 * NDB only; the default is to wait.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Holding the option
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\WaitOption(false))->keyword() // => 'WAIT'
 */
final class WaitOption implements StorageOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param bool $wait Whether WAIT (true) or NO_WAIT (false) is written
     */
    public function __construct(public readonly bool $wait, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Answers the keyword of the option.
     */
    public function keyword(): string
    {
        return 'WAIT';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->wait ? 'WAIT' : 'NO_WAIT');
    }
}
