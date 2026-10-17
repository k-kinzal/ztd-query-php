<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

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
 * @visibility public
 * @example Reading SET NAMES
 *     $set = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SET NAMES 'utf8mb4' COLLATE utf8mb4_bin");
 *     [$set->statement->items[0]->charset->name?->value, $set->statement->items[0]->collation?->name?->value, $set->toString()] // => ['utf8mb4', 'utf8mb4_bin', 'SET NAMES utf8mb4 COLLATE utf8mb4_bin']
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
     * Records notices of the explicitly written encoding names.
     */
    public function deriveItem(Derivation $derivation): void
    {
        if ($this->charset->name !== null) {
            \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::charset($this->charset->name->value, $derivation, $this->charset);
        }
        $notice = $this->collation?->deprecation($derivation->context->profile->grammar);
        if ($notice !== null) {
            $derivation->warn($notice, $this->collation);
        }
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
