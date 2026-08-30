<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Statement;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * The pg sql table sample.
 */
final class PgSqlTableSample
{
    /**
     * @throws InvalidDefinitionException When the sample could not describe anything
     */
    public function __construct(
        public readonly string $tableName,
        public readonly string $sourceSql,
        public readonly string $aliasSql,
        public readonly PgSqlTableSampleMethod $method,
        public readonly string $percentageSql,
        public readonly ?string $seedSql,
        public readonly int $startOffset,
        public readonly int $endOffset,
    ) {
        if ($tableName === '' || $sourceSql === '' || $percentageSql === '') {
            throw new InvalidDefinitionException('TABLESAMPLE fields must not be empty');
        }
        if ($startOffset < 0 || $endOffset <= $startOffset) {
            throw new InvalidDefinitionException('TABLESAMPLE offsets are invalid');
        }
    }
}
