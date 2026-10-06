<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

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
    use Snapshot;

    /**
     * @param Text $encryption The value, 'Y' or 'N' for the server
     */
    public function __construct(public readonly Text $encryption)
    {
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
        $out->keyword('ENCRYPTION')->node($this->encryption);
    }
}
