<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of performance_schema.status_by_thread: the session value of each status variable that has one, for each session connected.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-status-variable-tables.html.
 *
 * @visibility MySqlMemory
 */
final class StatusByThread implements SystemRows
{
    /**
     * Answers a row for each session status variable of each session.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $sessions = $reading->sessions();
        $rows = [];
        foreach ($sessions as $session) {
            $values = StatusVariables::of($reading->release)->values($reading->instance, false, true, count($sessions), connection: $session->id);
            foreach ($values as [$name, $value]) {
                $rows[] = ['THREAD_ID' => Variables::thread($session, $reading), 'VARIABLE_NAME' => $name, 'VARIABLE_VALUE' => $value];
            }
        }

        return $rows;
    }
}
