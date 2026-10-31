<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * User variables belonging to each connected session, preserving their first name spelling and binary values.
 *
 * Numeric values use their SQL text representation. An assigned NULL remains a row;
 * reading an unallocated variable creates no row. Verified on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-user-variable-tables.html.
 *
 * @visibility MySqlMemory
 */
final class UserVariablesByThread implements SystemRows
{
    /**
     * Answers the current user variables of each live session.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach ($reading->sessions() as $session) {
            foreach ($session->variables->user as $name => $entry) {
                $rows[] = [
                    'THREAD_ID' => Variables::thread($session, $reading),
                    'VARIABLE_NAME' => $entry->name ?? $name,
                    'VARIABLE_VALUE' => Convert::toText($entry->value, $entry->domain),
                ];
            }
        }

        return $rows;
    }
}
