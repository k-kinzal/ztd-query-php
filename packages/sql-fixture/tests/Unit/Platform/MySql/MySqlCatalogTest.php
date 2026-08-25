<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlCatalog;

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
}
