<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of performance_schema.variables_by_thread: the session value of each system variable that has one, for each session connected.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-system-variable-tables.html.
 *
 * @visibility MySqlMemory
 */
final class VariablesByThread implements SystemRows
{
    /**
     * Answers a row for each session variable of each session.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach ($reading->sessions() as $session) {
            foreach (Variables::system($session, false, true, $reading) as [$name, $value]) {
                $rows[] = ['THREAD_ID' => Variables::thread($session, $reading), 'VARIABLE_NAME' => $name, 'VARIABLE_VALUE' => $value];
            }
        }

        return $rows;
    }
}
