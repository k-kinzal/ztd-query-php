<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The settings saved by SET PERSIST and PERSIST_ONLY on this instance.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-persisted-variables-table.html.
 *
 * @visibility MySqlMemory
 */
final class PersistedVariables implements SystemRows
{
    /**
     * Answers saved values independently of the running global values.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach ($reading->session()?->variables->globals->persisted ?? [] as $name => $value) {
            $rows[] = ['VARIABLE_NAME' => $name, 'VARIABLE_VALUE' => $value ?? ''];
        }

        return $rows;
    }
}
