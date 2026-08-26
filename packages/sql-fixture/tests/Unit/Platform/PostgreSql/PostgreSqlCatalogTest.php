<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlCatalog;

#[CoversClass(PostgreSqlCatalog::class)]
final class PostgreSqlCatalogTest extends TestCase
{
    #[DataProvider('providerTableName')]
    public function testSplitSeparatesTheSchemaFromTheTable(string $written, string $schema, string $table): void
    {
        self::assertSame([$schema, $table], (new PostgreSqlCatalog())->split($written));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerTableName(): iterable
    {
        yield 'unqualified names a table in public' => ['users', 'public', 'users'];
        yield 'qualified names its own schema' => ['shop.users', 'shop', 'users'];
        yield 'only the first dot separates' => ['shop.users.x', 'shop', 'users.x'];
    }

    public function testColumnsOfNeedsAConnectionThatHasAnInformationSchema(): void
    {
        $this->expectException(PDOException::class);

        (new PostgreSqlCatalog())->columnsOf(new PDO('sqlite::memory:'), 'public', 'users');
    }

    public function testPrimaryKeysOfAnswersNoKeyWhereTheCatalogCannotBeRead(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        self::assertSame([], (new PostgreSqlCatalog())->primaryKeysOf($pdo, 'public', 'users'));
    }
}
