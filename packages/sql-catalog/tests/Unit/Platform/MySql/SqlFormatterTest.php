<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Platform\MySql\SqlFormatter::class)]
final class SqlFormatterTest extends TestCase
{
    public function testFormatExpandsAcceptedSyntaxAndDeclinesUnsupportedInput(): void
    {
        $formatter = new \SqlCatalog\Platform\MySql\SqlFormatter();
        self::assertSame("SELECT\n    1", $formatter->format('SELECT 1'));
        self::assertNull($formatter->format('this is not a statement'));
    }
}
