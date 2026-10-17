<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.KEYWORDS: the keywords of the release and whether each is reserved.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-keywords-table.html.
 *
 * @visibility MySqlMemory
 */
final class Keywords implements SystemRows
{
    /**
     * Answers a row for each keyword.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        return array_map(static fn (array $keyword): array => ['WORD' => $keyword[0], 'RESERVED' => $keyword[1]], ServerTables::of($reading->release)->keywords);
    }
}
