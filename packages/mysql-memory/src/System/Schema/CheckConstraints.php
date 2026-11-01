<?php

declare(strict_types=1);

namespace MySqlMemory\System\Schema;

use MySqlMemory\Dictionary\Check;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.CHECK_CONSTRAINTS: one for each CHECK constraint of each database.
 *
 * The constraints of a database are listed by name without regard to case. The clause is the
 * condition as SHOW CREATE TABLE writes it, a quote in it escaped with a backslash (verified
 * on a live 8.4.7 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-check-constraints-table.html.
 *
 * @visibility MySqlMemory
 */
final class CheckConstraints implements SystemRows
{
    /**
     * Answers a row for each CHECK constraint.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Listed::schemas($reading) as $schema) {
            $checks = [];
            foreach (Listed::of($schema, $reading) as $table) {
                if ($table instanceof StoredTable) {
                    array_push($checks, ...$table->definition->checks);
                }
            }
            usort($checks, static fn (Check $left, Check $right): int => strcasecmp($left->name, $right->name));
            foreach ($checks as $check) {
                $rows[] = ['CONSTRAINT_CATALOG' => 'def', 'CONSTRAINT_SCHEMA' => $schema->name, 'CONSTRAINT_NAME' => $check->name, 'CHECK_CLAUSE' => str_replace("'", "\\'", $check->text)];
            }
        }

        return $rows;
    }
}
