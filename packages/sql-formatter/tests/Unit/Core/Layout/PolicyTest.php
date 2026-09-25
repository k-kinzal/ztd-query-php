<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Layout\Policy;
use SqlFormatter\Core\Style;

#[CoversClass(Policy::class)]
#[CoversClass(FormatOptions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class PolicyTest extends TestCase
{
    #[TestWith([Style::Expanded, "\n  ", "\n    ", 4])]
    #[TestWith([Style::Tabular, "\n  ", '     ', 11])]
    #[TestWith([Style::River, "\n      ", ' ', 11])]
    public function testBeforeHeaderAndBodyPlacement(Style $style, string $before, string $after, int $indent): void
    {
        $policy = new Policy(new FormatOptions($style, 2), 2, 8);
        self::assertSame($before, $policy->beforeHeader(4));
        self::assertSame($after, $policy->afterHeader(4));
        self::assertSame($indent, $policy->bodyIndent());
    }
    public function testLineUsesRequestedColumn(): void
    {
        $policy = new Policy(new FormatOptions(), 0, 0);
        self::assertSame("\n   ", $policy->line(3));
    }

    public function testAfterHeaderBreaksExpandedBody(): void
    {
        $policy = new Policy(new FormatOptions(), 0, 6);
        self::assertSame("\n    ", $policy->afterHeader(6));
    }

    public function testBodyIndentUsesAlignedContentColumn(): void
    {
        $policy = new Policy(new FormatOptions(Style::River), 4, 8);
        self::assertSame(13, $policy->bodyIndent());
    }
}
