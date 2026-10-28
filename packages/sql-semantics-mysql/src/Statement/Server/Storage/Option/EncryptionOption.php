<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;

/**
 * `ENCRYPTION [=] {'Y' | 'N'}`: whether an InnoDB tablespace is encrypted (MySQL 8.0 and later).
 *
 * The equals sign is optional and not written. The server checks the value when it executes the statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Holding the option
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EncryptionOption(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('Y')))->encryption->value // => 'Y'
 */
final class EncryptionOption implements StorageOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Text $encryption The value, 'Y' or 'N' for the server
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly Text $encryption, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false)
    {
        Check::input($encryption->radix === null, 'A tablespace encryption value is quoted text.');
    }

    /**
     * Answers the keyword of the option.
     */
    public function keyword(): string
    {
        return 'ENCRYPTION';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('ENCRYPTION');
        if ($this->equals) {
            $out->symbol('=');
        }
        $out->node($this->encryption);
    }
}
