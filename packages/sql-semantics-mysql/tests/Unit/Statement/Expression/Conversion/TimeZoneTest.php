<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone;

#[CoversClass(TimeZone::class)]
#[Small]
final class TimeZoneTest extends TestCase
{
    public function testCasesExposeOnlyTheSupportedZoneSpellings(): void
    {
        self::assertSame(['UTC', '+00:00'], array_column(TimeZone::cases(), 'value'));
    }
}
