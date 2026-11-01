<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of performance_schema.session_variables, and of INFORMATION_SCHEMA.SESSION_VARIABLES in MySQL 5.6: the session value of each system variable, as SHOW SESSION VARIABLES lists them.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-system-variable-tables.html.
 *
 * @visibility MySqlMemory
 */
final class SessionVariables implements SystemRows
{
    /**
     * Answers a row for each system variable.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $session = $reading->session();

        return $session === null ? [] : Variables::rows(Variables::system($session, false, false, $reading), $reading);
    }
}
