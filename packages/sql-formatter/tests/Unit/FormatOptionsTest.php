<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlFormatter\FormatOptions;
use SqlFormatter\Style;

#[CoversClass(FormatOptions::class)]
final class FormatOptionsTest extends TestCase
{
    public function testDefaultLayout(): void
    {
        $options = new FormatOptions();
        self::assertSame(Style::Expanded, $options->style);
        self::assertSame(4, $options->indentWidth);
    }

    #[TestWith([1])]
    #[TestWith([16])]
    public function testIndentLimits(int $width): void
    {
        $options = new FormatOptions(Style::River, $width);
        self::assertSame($width, $options->indentWidth);
        self::assertSame(Style::River, $options->style);
    }

}
