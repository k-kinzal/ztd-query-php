<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion;

#[CoversClass(TimeZoneConversion::class)]
#[Medium]
final class TimeZoneConversionTest extends TestCase
{
    public function testResolvedOperandAndZoneConditionsRemainDistinct(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $type = $semantics->analyze("SELECT CAST(1 AT TIME ZONE 'bad' AS DATETIME)")->facts->diagnostics[0];
        $zone = $semantics->analyze("SELECT CAST(NULL AT TIME ZONE 'bad' AS DATETIME)")->facts->diagnostics[0];

        self::assertInstanceOf(TimeZoneConversion::class, $type);
        self::assertInstanceOf(TimeZoneConversion::class, $zone);
        self::assertTrue($type->operand);
        self::assertFalse($zone->operand);
        self::assertSame('Cannot cast value to TIMESTAMP WITH TIME ZONE.', $type->message());
        self::assertSame("Unknown or incorrect time zone: 'bad'", $zone->message());
    }
}
