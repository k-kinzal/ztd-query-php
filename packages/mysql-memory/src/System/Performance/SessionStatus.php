<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of performance_schema.session_status, and of INFORMATION_SCHEMA.SESSION_STATUS in MySQL 5.6: the session value of each status variable, as SHOW SESSION STATUS lists them.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-status-variable-tables.html.
 *
 * @visibility MySqlMemory
 */
final class SessionStatus implements SystemRows
{
    /**
     * Answers a row for each status variable.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        return Variables::rows(StatusVariables::of($reading->release)->values($reading->instance, false, false, count($reading->sessions()), connection: $reading->connection->id), $reading);
    }
}
