<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Bench;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;

/**
 * Measures uncached schema parsing with equivalent declarations in each dialect.
 *
 * A declaration is read with the grammar of its server, which costs
 * milliseconds rather than the microseconds text matching cost, so a
 * revolution count that keeps a run short is what makes the measurement
 * repeatable here.
 */
#[Bench\Groups(['schema'])]
#[Bench\Revs(50)]
final class SchemaParsingBench
{
    /**
     * Parses MySQL DDL without a provider cache.
     */
    public function benchMySql(): void
    {
        (new MySqlSchemaParser())->parse('CREATE TABLE items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(80) NOT NULL, price DECIMAL(8, 2), active BOOLEAN DEFAULT TRUE)');
    }

    /**
     * Parses PostgreSQL DDL without a provider cache.
     */
    public function benchPostgreSql(): void
    {
        (new PostgreSqlSchemaParser())->parse('CREATE TABLE items (id SERIAL PRIMARY KEY, name VARCHAR(80) NOT NULL, price NUMERIC(8, 2), active BOOLEAN DEFAULT TRUE)');
    }

    /**
     * Parses SQLite DDL without a provider cache.
     */
    public function benchSqlite(): void
    {
        (new SqliteSchemaParser())->parse('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(80) NOT NULL, price DECIMAL(8, 2), active BOOLEAN DEFAULT TRUE)');
    }
}
