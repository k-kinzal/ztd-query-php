<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use PDO;
use RuntimeException;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Fetches table schemas from MySQL databases using SHOW CREATE TABLE.
 */
final class MySqlSchemaFetcher implements SchemaFetcherInterface
{
    private MySqlSchemaParser $parser;

    public function __construct(?MySqlSchemaParser $parser = null)
    {
        $this->parser = $parser ?? new MySqlSchemaParser();
    }

    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        $createTableSql = $this->fetchCreateTableSql($pdo, $tableName);
        return $this->parser->parse($createTableSql);
    }

    /**
     * Fetch the CREATE TABLE SQL from the database.
     */
    private function fetchCreateTableSql(PDO $pdo, string $tableName): string
    {
        $quotedName = $this->quoteTableName($tableName);

        $stmt = $pdo->query("SHOW CREATE TABLE {$quotedName}");
        if ($stmt === false) {
            throw new RuntimeException("Failed to get CREATE TABLE for: {$tableName}");
        }

        /** @var array{0: string, 1: string}|false $row */
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row === false) {
            throw new RuntimeException("Table not found: {$tableName}");
        }

        return $row[1];
    }

    /**
     * Quote a table name for use in SQL.
     */
    private function quoteTableName(string $tableName): string
    {
        if (str_contains($tableName, '.')) {
            $parts = explode('.', $tableName, 2);
            return '`' . $parts[0] . '`.`' . $parts[1] . '`';
        }

        return '`' . $tableName . '`';
    }
}
