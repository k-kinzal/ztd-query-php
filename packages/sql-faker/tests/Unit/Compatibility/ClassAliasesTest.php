<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compatibility\SqlGeneratorFactory;
use SqlFaker\MySql\MySqlProvider;
use SqlFaker\PostgreSql\PostgreSqlProvider;
use SqlFaker\Sqlite\SqliteProvider;

#[CoversNothing]
final class ClassAliasesTest extends TestCase
{
    public function testOriginalProviderNamesResolveToThePlatformImplementations(): void
    {
        self::assertSame(MySqlProvider::class, (new \SqlFaker\MySqlProvider(Factory::create()))::class);
        self::assertSame(PostgreSqlProvider::class, (new \SqlFaker\PostgreSqlProvider(Factory::create()))::class);
        self::assertSame(SqliteProvider::class, (new \SqlFaker\SqliteProvider(Factory::create()))::class);
    }

    public function testOriginalFactoryNameResolvesToTheCompatibilityAdapter(): void
    {
        self::assertSame(SqlGeneratorFactory::class, (new \SqlFaker\Provider\SqlGeneratorFactory())::class);
    }
}
