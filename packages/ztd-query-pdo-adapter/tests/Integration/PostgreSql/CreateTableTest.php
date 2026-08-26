<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Connection\StatementInterface;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 *
 * @phpstan-import-type Row from StatementInterface
 */
#[CoversNothing]
#[Large]
final class CreateTableTest extends TestCase
{
    public function testCreateTableAndInsert(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");

            $ztdPdo->exec("INSERT INTO {$table} (id, name) VALUES (1, 'Alice')");

            $stmt = $ztdPdo->query("SELECT * FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $ztdRows = $stmt->fetchAll();

            self::assertCount(1, $ztdRows);
            self::assertSame(1, $ztdRows[0]['id']);
            self::assertSame('Alice', $ztdRows[0]['name']);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testCreateTableIfNotExists(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");

            $ztdPdo->exec("CREATE TABLE IF NOT EXISTS {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");

            $ztdPdo->exec("INSERT INTO {$table} (id, name) VALUES (1, 'Test')");

            $stmt = $ztdPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $ztdRows = $stmt->fetchAll();

            self::assertCount(1, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testCreateTableDoesNotModifyPhysicalDatabase(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");

            $stmt = $rawPdo->prepare(
                'SELECT table_name FROM information_schema.tables WHERE table_name = ? AND table_schema = current_schema()'
            );
            $stmt->execute([$table]);
            $rows = $stmt->fetchAll();
            self::assertCount(0, $rows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testCreateTableAsSelectPreservesColumnsForEmptyResult(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $source = 'source_' . bin2hex(random_bytes(8));
        $copy = 'copy_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$source} (id INTEGER, name TEXT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);
            $ztdPdo->exec("INSERT INTO {$source} VALUES (1, 'Alice')");

            self::assertSame(0, $ztdPdo->exec("CREATE TABLE {$copy} AS SELECT * FROM {$source} WHERE FALSE"));
            self::assertSame(1, $ztdPdo->exec("INSERT INTO {$copy} VALUES (2, 'Bob')"));

            $statement = $ztdPdo->query("SELECT * FROM {$copy} WHERE id = 2");
            self::assertNotFalse($statement);
            self::assertSame([['id' => 2, 'name' => 'Bob']], $statement->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testCreateTableAsSelectPreservesProjectedPostgreSqlTypes(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $source = 'source_' . bin2hex(random_bytes(8));
        $copy = 'copy_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$source} (id INTEGER, name VARCHAR(40), score NUMERIC(8, 2))");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);
            $ztdPdo->exec("INSERT INTO {$source} VALUES (1, 'Alice', 95.25)");

            $ztdPdo->exec(
                "CREATE TABLE {$copy} AS SELECT id + 1 AS next_id, name, score FROM {$source}"
            );

            $statement = $ztdPdo->query("SELECT next_id, name, score FROM {$copy} WHERE next_id = 2");
            self::assertNotFalse($statement);
            self::assertSame(
                [['next_id' => 2, 'name' => 'Alice', 'score' => '95.25']],
                $statement->fetchAll(),
            );

            $typeStatement = $ztdPdo->query(
                'SELECT pg_typeof(next_id)::text AS id_type, pg_typeof(name)::text AS name_type, '
                . "pg_typeof(score)::text AS score_type FROM {$copy} LIMIT 1"
            );
            self::assertNotFalse($typeStatement);
            self::assertSame(
                [['id_type' => 'integer', 'name_type' => 'character varying', 'score_type' => 'numeric']],
                $typeStatement->fetchAll(),
            );
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
