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
    public function testMessageDistinguishesResolvedOperandAndZoneConditions(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $conversion = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('bad'));
        $type = $conversion->problem();
        $zone = $semantics->analyze("SELECT CAST(NULL AT TIME ZONE 'bad' AS DATETIME)")->facts->diagnostics[0];

        self::assertInstanceOf(TimeZoneConversion::class, $zone);
        self::assertTrue($type->operand);
        self::assertFalse($zone->operand);
        self::assertSame('Cannot cast value to TIMESTAMP WITH TIME ZONE.', $type->message());
        self::assertSame("Unknown or incorrect time zone: 'bad'", $zone->message());
    }
}
