<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

#[\PHPUnit\Framework\Attributes\CoversNothing]
final class DialectFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testForParserUsesEachBuiltInImplementation(): void
    {
        self::assertInstanceOf(\SqlFormatter\Platform\MySql\Dialect::class, \SqlFormatter\Facade\DialectFactory::forParser(new \SqlParser\MySql\MySqlParser()));
        self::assertInstanceOf(\SqlFormatter\Platform\PostgreSql\Dialect::class, \SqlFormatter\Facade\DialectFactory::forParser(new \SqlParser\PostgreSql\PostgreSqlParser()));
        self::assertInstanceOf(\SqlFormatter\Platform\Sqlite\Dialect::class, \SqlFormatter\Facade\DialectFactory::forParser(new \SqlParser\Sqlite\SqliteParser()));
    }

}
