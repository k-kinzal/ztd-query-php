<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderInteger;

#[CoversClass(HeaderInteger::class)]
#[Small]
final class HeaderIntegerTest extends TestCase
{
    public function testValueKeepsBothLimitsAndZero(): void
    {
        self::assertSame(-2147483648, (new HeaderInteger(-2147483648))->value);
        self::assertSame(2147483647, (new HeaderInteger(2147483647))->value);
        self::assertSame(0, (new HeaderInteger(0))->value);
    }
}
