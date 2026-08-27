<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFixture\Platform\MySql\MySqlCatalog;
use Tests\Fixture\RecordingPdo;

#[CoversClass(MySqlCatalog::class)]
final class MySqlCatalogTest extends TestCase
{
    #[DataProvider('providerTableName')]
    public function testQuotedWritesEachIdentifierInItsOwnBackquotes(string $written, string $quoted): void
    {
        self::assertSame($quoted, (new MySqlCatalog())->quoted($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerTableName(): iterable
    {
        yield 'bare' => ['users', '`users`'];
        yield 'database qualified' => ['shop.users', '`shop`.`users`'];
        yield 'a name that needs quoting' => ['order by', '`order by`'];
    }

    public function testCreateTableSqlOfReportsAConnectionThatWillNotAnswer(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get CREATE TABLE for: users');

        (new MySqlCatalog())->createTableSqlOf($pdo, 'users');
    }
    public function testCreateTableSqlOfAsksTheServerHowTheTableWasCreated(): void
    {
        $pdo = new RecordingPdo();

        try {
            (new MySqlCatalog())->createTableSqlOf($pdo, 'orders');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        self::assertSame(['SHOW CREATE TABLE `orders`'], $pdo->queried);
    }

    public function testCreateTableSqlOfQuotesEachHalfOfAQualifiedName(): void
    {
        $pdo = new RecordingPdo();

        try {
            (new MySqlCatalog())->createTableSqlOf($pdo, 'shop.orders');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        self::assertSame(['SHOW CREATE TABLE `shop`.`orders`'], $pdo->queried);
    }
}
