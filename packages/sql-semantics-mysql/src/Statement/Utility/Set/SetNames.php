<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;

/**
 * SET NAMES: sets the client, connection and result character sets, and the connection collation.
 *
 * Rule: MYSQL-SET-NAMES-001. `SET NAMES cs [COLLATE co]` sets
 * character_set_client, character_set_connection and
 * character_set_results to `cs` and collation_connection to `co`, or to
 * the default collation of `cs`; `SET NAMES DEFAULT` maps the three to the
 * default character set. The character set and collation are names the
 * server resolves; the context holds no character set catalog, so a
 * collation of another character set (ER_COLLATION_CHARSET_MISMATCH) is not
 * detected. Facts: none. Terminates: the parts are leaves.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-names.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class SetNames implements SetItem
{
    use Snapshot;

    /**
     * @param CharsetName $charset The character set, or DEFAULT
     * @param CollationName|null $collation The collation of the connection; MySQL 5.x also accepts COLLATE DEFAULT
     */
    public function __construct(public readonly CharsetName $charset, public readonly ?CollationName $collation = null)
    {
    }

    /**
     * Derives nothing: the names are resolved by the server.
     */
    public function deriveItem(Derivation $derivation): void
    {
    }

    /**
     * Writes NAMES, the character set and the collation.
     */
    public function render(Output $out): void
    {
        $out->keyword('NAMES')->node($this->charset);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->node($this->collation);
        }
    }
}
