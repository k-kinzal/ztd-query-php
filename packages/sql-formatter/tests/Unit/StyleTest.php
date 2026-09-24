<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlFormatter\Style;

#[\PHPUnit\Framework\Attributes\CoversNothing]
final class StyleTest extends TestCase
{
    public function testCasesExposeFourStablePresetNames(): void
    {
        self::assertSame(['compact', 'expanded', 'tabular', 'river'], array_column(Style::cases(), 'value'));
    }
}
