<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversNothing]
#[Large]
final class AlterTableTest extends TestCase
{
    public function testAddColumnMigratesExistingRowsAndDefaultsFutureRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE people (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        self::assertSame(1, $ztdPdo->exec("INSERT INTO people VALUES (1, 'Alice')"));
        self::assertSame(0, $ztdPdo->exec('ALTER TABLE people ADD COLUMN age INTEGER NOT NULL DEFAULT 7'));
        self::assertSame(1, $ztdPdo->exec("INSERT INTO people (id, name) VALUES (2, 'Bob')"));
        self::assertSame(1, $ztdPdo->exec('UPDATE people SET age = 30 WHERE id = 1'));

        $statement = $ztdPdo->query('SELECT id, name, age FROM people WHERE age >= 7 ORDER BY age, id');
        self::assertNotFalse($statement);
        self::assertSame([
            ['id' => 2, 'name' => 'Bob', 'age' => 7],
            ['id' => 1, 'name' => 'Alice', 'age' => 30],
        ], $statement->fetchAll());

        $physicalColumns = $rawPdo->query('PRAGMA table_info(people)');
        self::assertNotFalse($physicalColumns);
        self::assertSame(['id', 'name'], array_column($physicalColumns->fetchAll(), 'name'));
    }

    public function testRenameTableMovesSchemaAndRowsWithoutTouchingPhysicalTable(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE source_table (id INTEGER PRIMARY KEY, value TEXT)');
        $rawPdo->exec("INSERT INTO source_table VALUES (99, 'physical')");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $ztdPdo->exec("INSERT INTO source_table VALUES (1, 'shadow')");
        $ztdPdo->exec('ALTER TABLE source_table RENAME TO target_table');

        $statement = $ztdPdo->query('SELECT * FROM target_table');
        self::assertNotFalse($statement);
        self::assertSame([['id' => 1, 'value' => 'shadow']], $statement->fetchAll());
        $physicalSource = $rawPdo->query("SELECT name FROM sqlite_master WHERE name = 'source_table'");
        self::assertNotFalse($physicalSource);
        self::assertSame('source_table', $physicalSource->fetchColumn());
        $physicalTarget = $rawPdo->query("SELECT name FROM sqlite_master WHERE name = 'target_table'");
        self::assertNotFalse($physicalTarget);
        self::assertFalse($physicalTarget->fetchColumn());

        $oldTable = $ztdPdo->query('SELECT * FROM source_table');
        self::assertNotFalse($oldTable);
        self::assertSame([], $oldTable->fetchAll());
    }

    public function testRenameAndDropColumnMigrateStoredRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, label TEXT, obsolete TEXT)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $ztdPdo->exec("INSERT INTO records VALUES (1, 'kept', 'removed')");
        $ztdPdo->exec('ALTER TABLE records RENAME COLUMN label TO title');
        $ztdPdo->exec('ALTER TABLE records DROP COLUMN obsolete');

        $statement = $ztdPdo->query('SELECT id, title FROM records');
        self::assertNotFalse($statement);
        self::assertSame([['id' => 1, 'title' => 'kept']], $statement->fetchAll());
    }
}
