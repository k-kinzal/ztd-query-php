<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Reset;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;

/**
 * `RESET QUERY CACHE`: removes every query result from the query cache (MySQL 5.6 and 5.7; 8.0 removed the cache).
 *
 * Mirrors the REFRESH_QUERY_CACHE flag of SQLCOM_RESET.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/query-cache-status-and-maintenance.html.
 *
 * @visibility SqlSemantics
 */
final class ResetQueryCache implements ResetTarget
{
    use Snapshot;

    /**
     * Checks that the release has a query cache.
     */
    public function deriveTarget(Derivation $derivation): void
    {
        Check::input((new Releases())->legacy($derivation->context->profile->grammar), 'RESET QUERY CACHE needs MySQL 5.6 or 5.7.');
    }

    /**
     * Writes the item.
     */
    public function render(Output $out): void
    {
        $out->keyword('QUERY', 'CACHE');
    }
}
