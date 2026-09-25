<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Platform\Sqlite\Dialect::class)]
final class DialectTest extends TestCase
{
    public function testIdentifierQuoteMatchesTheLanguage(): void
    {
        self::assertSame('"', (new \SqlCatalog\Platform\Sqlite\Dialect())->identifierQuote());
    }

    public function testInsertPrefixPreservesConflictHandling(): void
    {
        self::assertSame('insert into ', (new \SqlCatalog\Platform\Sqlite\Dialect())->insertPrefix(false));
        self::assertSame('insert or ignore into ', (new \SqlCatalog\Platform\Sqlite\Dialect())->insertPrefix(true));
    }

    public function testInsertSuffixPreservesConflictHandling(): void
    {
        self::assertSame('', (new \SqlCatalog\Platform\Sqlite\Dialect())->insertSuffix(false));
        self::assertSame('', (new \SqlCatalog\Platform\Sqlite\Dialect())->insertSuffix(true));
    }
}
