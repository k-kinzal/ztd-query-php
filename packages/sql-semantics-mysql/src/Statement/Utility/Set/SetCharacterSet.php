<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;

/**
 * SET CHARACTER SET: sets the client and result character sets and makes the connection use the database character set.
 *
 * Rule: MYSQL-SET-CHARSET-001. `SET CHARACTER SET cs` sets
 * character_set_client and character_set_results to `cs` and
 * character_set_connection to the value of character_set_database;
 * `DEFAULT` restores the defaults. `CHARACTER SET`, `CHAR SET` and
 * `CHARSET` are the same keyword (LeafNoise); the writer emits CHARSET.
 * Facts: none. Terminates: the part is a leaf.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-character-set.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class SetCharacterSet implements SetItem
{
    use Snapshot;

    /**
     * @param CharsetName $charset The character set, or DEFAULT
     */
    public function __construct(public readonly CharsetName $charset)
    {
    }

    /**
     * Derives nothing: the name is resolved by the server.
     */
    public function deriveItem(Derivation $derivation): void
    {
    }

    /**
     * Writes CHARSET and the character set.
     */
    public function render(Output $out): void
    {
        $out->keyword('CHARSET')->node($this->charset);
    }
}
