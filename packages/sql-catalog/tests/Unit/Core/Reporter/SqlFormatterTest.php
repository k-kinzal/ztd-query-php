<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Reporter;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Reporter\Html\SqlFormatter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Core\Catalog\StatementPart::class)]
final class SqlFormatterTest extends TestCase
{
    public function testFormatUsesAnApplicationPolicyAndTriesTheNextOnDecline(): void
    {
        $declining = self::createStub(\SqlCatalog\Core\Reporter\SqlFormatter::class);
        $declining->method('format')->willReturn(null);
        $formatting = self::createStub(\SqlCatalog\Core\Reporter\SqlFormatter::class);
        $formatting->method('format')->willReturn("SELECT\n    1");
        $formatter = new \SqlCatalog\Reporter\Html\SqlFormatter([$declining, $formatting]);
        $parts = $formatter->format([new \SqlCatalog\Core\Catalog\StatementPart('SELECT 1')]);
        self::assertSame("SELECT\n    1", $parts[0]->text);
    }
}
