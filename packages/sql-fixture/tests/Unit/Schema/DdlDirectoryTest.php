<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFixture\Platform\Sqlite\SqliteColumnReader;
use SqlFixture\Platform\Sqlite\SqliteCreateTable;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\DdlDirectory;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\TableSchema;

#[CoversClass(DdlDirectory::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(SqliteColumnReader::class)]
#[UsesClass(SqliteCreateTable::class)]
#[UsesClass(SqliteSchemaParser::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(SchemaParseException::class)]
final class DdlDirectoryTest extends TestCase
{
    public function testTablesReadsEveryTableTheDirectoryDeclares(): void
    {
        $directory = sys_get_temp_dir() . '/sql-fixture-ddl-' . getmypid();
        mkdir($directory, 0755, true);
        file_put_contents($directory . '/users.sql', 'CREATE TABLE Users (id INTEGER)');
        file_put_contents($directory . '/orders.sql', 'CREATE TABLE orders (id INTEGER)');

        self::assertSame(['orders', 'users'], array_keys((new DdlDirectory(new SqliteSchemaParser()))->tables($directory)));

        unlink($directory . '/users.sql');
        unlink($directory . '/orders.sql');
        rmdir($directory);
    }

    public function testTablesPassesOverAFileThatDeclaresNoTable(): void
    {
        $directory = sys_get_temp_dir() . '/sql-fixture-ddl-other-' . getmypid();
        mkdir($directory, 0755, true);
        file_put_contents($directory . '/grants.sql', 'GRANT SELECT ON users TO app');

        self::assertSame([], (new DdlDirectory(new SqliteSchemaParser()))->tables($directory));

        unlink($directory . '/grants.sql');
        rmdir($directory);
    }

    public function testTablesRefusesAPathThatIsNotADirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DDL path is not a directory');

        (new DdlDirectory(new SqliteSchemaParser()))->tables(sys_get_temp_dir() . '/no-such-ddl-directory');
    }

    public function testTableInReadsTheTableOneFileDeclares(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ddl-');
        self::assertIsString($path);
        file_put_contents($path, "-- a note\nCREATE TABLE users (id INTEGER)");

        $table = (new DdlDirectory(new SqliteSchemaParser()))->tableIn($path);

        self::assertNotNull($table);
        self::assertSame('users', $table->tableName);

        unlink($path);
    }

    public function testTableInAnswersNothingForAFileHoldingOnlyComments(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ddl-');
        self::assertIsString($path);
        file_put_contents($path, "-- nothing but a note\n");

        self::assertNull((new DdlDirectory(new SqliteSchemaParser()))->tableIn($path));

        unlink($path);
    }

    public function testTableInRefusesAFileItCannotRead(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to read file');

        (new DdlDirectory(new SqliteSchemaParser()))->tableIn(sys_get_temp_dir() . '/no-such-ddl-file.sql');
    }
}
