<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Source;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The parenthesized server identifiers of IGNORE_SERVER_IDS; an empty list clears the setting.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html#crs-opt-ignore_server_ids.
 *
 * @visibility public
 * @example Holding the identifiers in order
 *     (new \SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds([new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2')]))->ids[0]->text // => '2'
 */
final class ServerIds implements Node
{
    use Snapshot;

    /**
     * @var list<Numeral> The server identifiers in written order
     */
    public readonly array $ids;

    /**
     * @param list<Numeral> $ids The server identifiers in written order; empty to clear the setting
     */
    public function __construct(array $ids)
    {
        $this->ids = Check::listOf($ids, Numeral::class, 'IGNORE_SERVER_IDS lists numbers.');
    }

    /**
     * Writes the list in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->ids)->symbol(')');
    }
}
