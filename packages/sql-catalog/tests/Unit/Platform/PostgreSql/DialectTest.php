<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Platform\PostgreSql\Dialect::class)]
final class DialectTest extends TestCase
{
    public function testIdentifierQuoteMatchesTheLanguage(): void
    {
        self::assertSame('"', (new \SqlCatalog\Platform\PostgreSql\Dialect())->identifierQuote());
    }

    public function testInsertPrefixPreservesConflictHandling(): void
    {
        self::assertSame('insert into ', (new \SqlCatalog\Platform\PostgreSql\Dialect())->insertPrefix(false));
        self::assertSame('insert into ', (new \SqlCatalog\Platform\PostgreSql\Dialect())->insertPrefix(true));
    }

    public function testInsertSuffixPreservesConflictHandling(): void
    {
        self::assertSame('', (new \SqlCatalog\Platform\PostgreSql\Dialect())->insertSuffix(false));
        self::assertSame(' on conflict do nothing', (new \SqlCatalog\Platform\PostgreSql\Dialect())->insertSuffix(true));
    }
}
