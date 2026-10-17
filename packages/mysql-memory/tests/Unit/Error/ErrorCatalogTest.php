<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\ErrorCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorCatalog::class)]
#[Small]
final class ErrorCatalogTest extends TestCase
{
    public function testInstanceAnswersTheSameCatalogEveryTime(): void
    {
        self::assertSame(ErrorCatalog::instance(), ErrorCatalog::instance());
    }

    public function testInstanceReadsTheFormatsOfTheServerErrorReference(): void
    {
        $catalog = ErrorCatalog::instance();

        self::assertSame(['42S02', "Table '%s.%s' doesn't exist"], $catalog->entry(1146));
        self::assertSame(['3D000', 'No database selected'], $catalog->entry(1046));
        self::assertSame(['23000', "Duplicate entry '%s' for key '%s'"], $catalog->entry(1062));
    }

    public function testEntryAnswersTheEntryOfAnErrorNumber(): void
    {
        $catalog = new ErrorCatalog([1065 => ['42000', 'Query was empty']]);

        self::assertSame(['42000', 'Query was empty'], $catalog->entry(1065));
    }

    public function testEntryAnswersUnknownErrorForAnUnlistedNumber(): void
    {
        $catalog = new ErrorCatalog([1065 => ['42000', 'Query was empty']]);

        self::assertSame(['HY000', 'Unknown error'], $catalog->entry(1046));
    }
}
