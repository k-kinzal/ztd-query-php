<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFixture\Platform\Sqlite\SqliteCatalog;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(SqliteCatalog::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
final class SqliteCatalogTest extends TestCase
{
    #[Test]
    public function testCreateTableSqlOfAnswersTheStatementTheTableWasCreatedWith(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        self::assertSame(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)',
            (new SqliteCatalog())->createTableSqlOf($pdo, 'users')
        );
    }

    #[Test]
    public function testCreateTableSqlOfIsNullWhereTheConnectionKnowsNoSuchTable(): void
    {
        self::assertNull((new SqliteCatalog())->createTableSqlOf(new PDO('sqlite::memory:'), 'users'));
    }

    #[Test]
    public function testCreateTableSqlOfIsNullForAViewRatherThanATable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE VIEW active_users AS SELECT * FROM users');

        self::assertNull((new SqliteCatalog())->createTableSqlOf($pdo, 'active_users'));
    }

    #[Test]
    public function testTableInfoNamesEveryColumnThePragmaReports(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        $schema = (new SqliteCatalog())->tableInfo($pdo, 'users');

        self::assertSame('users', $schema->tableName);
        self::assertSame(['id', 'name'], array_keys($schema->columns));
        self::assertSame(['id'], $schema->primaryKeys);
    }

    #[Test]
    public function testTableInfoReadsALengthOffTheDeclaredType(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (name VARCHAR(64))');

        $column = (new SqliteCatalog())->tableInfo($pdo, 'users')->columns['name'];

        self::assertSame('VARCHAR', $column->type);
        self::assertSame(64, $column->length);
        self::assertNull($column->precision);
    }

    #[Test]
    public function testTableInfoReadsAPrecisionAndScaleOffTheDeclaredType(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE prices (amount DECIMAL(8, 2))');

        $column = (new SqliteCatalog())->tableInfo($pdo, 'prices')->columns['amount'];

        self::assertSame('DECIMAL', $column->type);
        self::assertSame(8, $column->precision);
        self::assertSame(2, $column->scale);
        self::assertNull($column->length);
    }

    #[Test]
    public function testTableInfoCallsAColumnDeclaredWithoutATypeABlob(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE anything (payload)');

        self::assertSame('BLOB', (new SqliteCatalog())->tableInfo($pdo, 'anything')->columns['payload']->type);
    }

    #[Test]
    public function testTableInfoSeesAColumnThatMayNotBeNull(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, note TEXT)');

        $schema = (new SqliteCatalog())->tableInfo($pdo, 'users');

        self::assertFalse($schema->columns['name']->nullable);
        self::assertTrue($schema->columns['note']->nullable);
    }

    #[Test]
    public function testTableInfoRefusesATableTheConnectionDoesNotKnow(): void
    {
        $this->expectException(RuntimeException::class);

        (new SqliteCatalog())->tableInfo(new PDO('sqlite::memory:'), 'users');
    }

    #[Test]
    public function testTableInfoWritesOnlyIdentifierCharactersIntoThePragma(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');

        $schema = (new SqliteCatalog())->tableInfo($pdo, '"users"');

        self::assertSame(['id'], array_keys($schema->columns));
        self::assertSame('"users"', $schema->tableName);
    }

    #[Test]
    #[DataProvider('providerDefaultAsThePragmaReportsIt')]
    public function testDefaultValueOfReadsADefaultAsTheTypeItIsWrittenAs(?string $written, mixed $expected): void
    {
        self::assertSame($expected, (new SqliteCatalog())->defaultValueOf($written));
    }

    /**
     * @return list<array{string|null, int|float|string|null}>
     */
    public static function providerDefaultAsThePragmaReportsIt(): array
    {
        return [
            [null, null],
            ['NULL', null],
            ["'ready'", 'ready'],
            ['"ready"', 'ready'],
            ['7', 7],
            ['1.5', 1.5],
            ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
        ];
    }
    public function testCreateTableSqlOfAsksSqliteForTheTableItWasNamed(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER)');
        $pdo->exec('CREATE TABLE orders (id INTEGER)');

        self::assertSame('CREATE TABLE orders (id INTEGER)', (new SqliteCatalog())->createTableSqlOf($pdo, 'orders'));
    }

    public function testTableInfoSaysSqliteHasNoUnsignedColumn(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER)');

        $schema = (new SqliteCatalog())->tableInfo($pdo, 'users');

        self::assertFalse($schema->columns['id']->unsigned);
    }

    public function testTableInfoLeavesTheStatementToSayWhatCountsUpOrIsGenerated(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT)');

        $schema = (new SqliteCatalog())->tableInfo($pdo, 'users');

        self::assertSame(
            [false, false],
            [$schema->columns['id']->autoIncrement, $schema->columns['id']->generated],
        );
    }

    public function testTableInfoNamesOnlyTheColumnsTheKeyIsMadeOf(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE lines (order_id INTEGER, line_no INTEGER, note TEXT, PRIMARY KEY (order_id, line_no))');

        $schema = (new SqliteCatalog())->tableInfo($pdo, 'lines');

        self::assertSame(['order_id', 'line_no'], $schema->primaryKeys);
    }

    public function testTableInfoReadsATypeWrittenInLowerCaseAsTheTypeItNames(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (name varchar(30))');

        $schema = (new SqliteCatalog())->tableInfo($pdo, 'users');

        self::assertSame(['VARCHAR', 30], [$schema->columns['name']->type, $schema->columns['name']->length]);
    }

    public function testDefaultValueOfReadsNullWrittenInLowerCaseAsNothing(): void
    {
        self::assertNull((new SqliteCatalog())->defaultValueOf('null'));
    }

    public function testDefaultValueOfKeepsTheWholeQuotedTextIncludingItsLineBreaks(): void
    {
        self::assertSame("one\ntwo", (new SqliteCatalog())->defaultValueOf("'one\ntwo'"));
    }
}
