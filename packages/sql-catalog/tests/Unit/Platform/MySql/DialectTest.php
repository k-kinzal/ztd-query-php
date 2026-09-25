<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Platform\MySql\Dialect::class)]
final class DialectTest extends TestCase
{
    public function testIdentifierQuoteMatchesTheLanguage(): void
    {
        self::assertSame('`', (new \SqlCatalog\Platform\MySql\Dialect())->identifierQuote());
    }

    public function testInsertPrefixPreservesConflictHandling(): void
    {
        self::assertSame('insert into ', (new \SqlCatalog\Platform\MySql\Dialect())->insertPrefix(false));
        self::assertSame('insert ignore into ', (new \SqlCatalog\Platform\MySql\Dialect())->insertPrefix(true));
    }

    public function testInsertSuffixPreservesConflictHandling(): void
    {
        self::assertSame('', (new \SqlCatalog\Platform\MySql\Dialect())->insertSuffix(false));
        self::assertSame('', (new \SqlCatalog\Platform\MySql\Dialect())->insertSuffix(true));
    }

    public function testReturningSuffixNamesHowTheGeneratedKeyIsRead(): void
    {
        self::assertSame('', (new \SqlCatalog\Platform\MySql\Dialect())->returningSuffix());
    }
}
