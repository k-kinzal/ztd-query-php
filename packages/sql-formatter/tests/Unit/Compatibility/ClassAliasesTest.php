<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

#[\PHPUnit\Framework\Attributes\CoversNothing]
final class ClassAliasesTest extends \PHPUnit\Framework\TestCase
{
    public function testOriginalEntryPointsRemainUsable(): void
    {
        $formatter = new \SqlFormatter\Formatter(new \SqlParser\Sqlite\SqliteParser(), new \SqlFormatter\FormatOptions(\SqlFormatter\Style::Compact));
        self::assertSame('SELECT 1', $formatter->format('select 1;'));
        self::assertSame('preserved', (new \SqlFormatter\FormattingException('preserved'))->getMessage());
    }
}
