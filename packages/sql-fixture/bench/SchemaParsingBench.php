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
 * A parser loads the tables of its grammar when it is built, which a caller
 * does once and a measurement should not do at all, so the parsers are built
 * before the measured statement is read.
 */
#[Bench\Groups(['schema'])]
#[Bench\BeforeMethods('setUp')]
#[Bench\Revs(100)]
final class SchemaParsingBench
{
    private MySqlSchemaParser $mysql;

    private PostgreSqlSchemaParser $postgres;

    private SqliteSchemaParser $sqlite;

    /**
     * Loads the grammar tables of each dialect outside the measured operation.
     */
    public function setUp(): void
    {
        $this->mysql = new MySqlSchemaParser();
        $this->postgres = new PostgreSqlSchemaParser();
        $this->sqlite = new SqliteSchemaParser();
    }

    /**
     * Parses MySQL DDL without a provider cache.
     */
    public function benchMySql(): void
    {
        $this->mysql->parse('CREATE TABLE items (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(80) NOT NULL, price DECIMAL(8, 2), active BOOLEAN DEFAULT TRUE)');
    }

    /**
     * Parses PostgreSQL DDL without a provider cache.
     */
    public function benchPostgreSql(): void
    {
        $this->postgres->parse('CREATE TABLE items (id SERIAL PRIMARY KEY, name VARCHAR(80) NOT NULL, price NUMERIC(8, 2), active BOOLEAN DEFAULT TRUE)');
    }

    /**
     * Parses SQLite DDL without a provider cache.
     */
    public function benchSqlite(): void
    {
        $this->sqlite->parse('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(80) NOT NULL, price DECIMAL(8, 2), active BOOLEAN DEFAULT TRUE)');
    }
}
