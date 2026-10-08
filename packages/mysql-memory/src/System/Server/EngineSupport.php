<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.ENGINES: the storage engines a server of the release has, in the order it lists them.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-engines-table.html.
 *
 * @visibility MySqlMemory
 */
final class EngineSupport implements SystemRows
{
    /**
     * Answers a row for each storage engine.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $names = ['ENGINE', 'SUPPORT', 'COMMENT', 'TRANSACTIONS', 'XA', 'SAVEPOINTS'];

        return array_map(static fn (array $engine): array => array_combine($names, $engine), ServerTables::of($reading->release)->engines);
    }
}
