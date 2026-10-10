<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of performance_schema.global_status, and of INFORMATION_SCHEMA.GLOBAL_STATUS in MySQL 5.6: the global value of each status variable, as SHOW GLOBAL STATUS lists them.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-status-variable-tables.html.
 *
 * @visibility MySqlMemory
 */
final class GlobalStatus implements SystemRows
{
    /**
     * Answers a row for each status variable.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        return Variables::rows(StatusVariables::of($reading->release)->values($reading->instance, true, false, count($reading->sessions()), instant: $reading->connection->context->started), $reading);
    }
}
