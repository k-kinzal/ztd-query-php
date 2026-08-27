<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlCatalog;
use Tests\Fixture\RecordingPdo;

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
    public function testColumnsOfAsksTheInformationSchemaForEverythingADeclarationNeeds(): void
    {
        $pdo = new RecordingPdo();

        (new PostgreSqlCatalog())->columnsOf($pdo, 'public', 'orders');

        self::assertSame(
            [
                'SELECT column_name, data_type, character_maximum_length, '
                . 'numeric_precision, numeric_scale, is_nullable, column_default, '
                . 'udt_name '
                . 'FROM information_schema.columns '
                . 'WHERE table_schema = :schema AND table_name = :table '
                . 'ORDER BY ordinal_position',
            ],
            $pdo->prepared,
        );
    }

    public function testPrimaryKeysOfAsksTheIndexCatalogWhichColumnsTheKeyIsMadeOf(): void
    {
        $pdo = new RecordingPdo();

        (new PostgreSqlCatalog())->primaryKeysOf($pdo, 'public', 'orders');

        self::assertSame(
            [
                'SELECT a.attname '
                . 'FROM pg_index i '
                . 'JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) '
                . 'WHERE i.indrelid = :table_oid::regclass AND i.indisprimary',
            ],
            $pdo->prepared,
        );
    }
}
