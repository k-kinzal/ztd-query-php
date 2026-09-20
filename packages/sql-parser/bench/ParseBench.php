<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Benchmark;
use SqlParser\MySql\MySqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;
use SqlParser\Table\TableFile;

/**
 * Measures loading a parse table and parsing a statement with each dialect.
 */
final class ParseBench
{
    private MySqlParser $mysql;

    private PostgreSqlParser $postgres;

    private SqliteParser $sqlite;

    /**
     * Loads every parser before measurement.
     */
    public function setUp(): void
    {
        $this->mysql = new MySqlParser();
        $this->postgres = new PostgreSqlParser();
        $this->sqlite = new SqliteParser();
    }

    /**
     * Lists a statement per dialect.
     *
     * @return array<string, array{dialect: 'mysql'|'postgres'|'sqlite', sql: string}>
     */
    public function provideStatements(): array
    {
        return [
            'mysql' => ['dialect' => 'mysql', 'sql' => "SELECT u.id, COUNT(*) AS n FROM users u JOIN orders o ON o.user_id = u.id WHERE u.name LIKE 'a%' AND o.total > ? GROUP BY u.id HAVING n > 1 ORDER BY n DESC LIMIT 10"],
            'postgres' => ['dialect' => 'postgres', 'sql' => "SELECT u.id, count(*) AS n FROM users u JOIN orders o ON o.user_id = u.id WHERE u.name LIKE 'a%' AND o.total > $1 GROUP BY u.id HAVING count(*) > 1 ORDER BY n DESC LIMIT 10"],
            'sqlite' => ['dialect' => 'sqlite', 'sql' => "SELECT u.id, count(*) AS n FROM users u JOIN orders o ON o.user_id = u.id WHERE u.name LIKE 'a%' AND o.total > ? GROUP BY u.id HAVING n > 1 ORDER BY n DESC LIMIT 10"],
        ];
    }

    /**
     * Parses one statement with a loaded parser.
     *
     * @param array{dialect: 'mysql'|'postgres'|'sqlite', sql: string} $params
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\ParamProviders('provideStatements')]
    #[Benchmark\Revs(200)]
    #[Benchmark\Iterations(5)]
    public function benchParse(array $params): void
    {
        match ($params['dialect']) {
            'mysql' => $this->mysql->parse($params['sql']),
            'postgres' => $this->postgres->parse($params['sql']),
            'sqlite' => $this->sqlite->parse($params['sql']),
        };
    }

    /**
     * Loads the default MySQL parse table from its file.
     */
    #[Benchmark\Revs(20)]
    #[Benchmark\Iterations(5)]
    public function benchLoadTable(): void
    {
        TableFile::forget();
        new MySqlParser();
    }
}
