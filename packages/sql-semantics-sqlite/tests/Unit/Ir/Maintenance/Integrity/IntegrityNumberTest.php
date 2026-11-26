<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Integrity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityNumber;

#[CoversClass(IntegrityNumber::class)]
#[Small]
final class IntegrityNumberTest extends TestCase
{
    public function testLimitReadsOnlyTheNativeIntegerPrefix(): void
    {
        $number = new IntegrityNumber();

        self::assertSame(12, $number->limit('00012.9'));
        self::assertSame(10, $number->limit('0x000a'));
        self::assertSame(100, $number->limit('0e9'));
        self::assertNull($number->limit('0x80000000'));
        self::assertNull($number->limit('.5'));
    }
}
