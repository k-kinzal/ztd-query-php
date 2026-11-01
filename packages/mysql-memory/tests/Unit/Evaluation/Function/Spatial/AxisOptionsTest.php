<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Function\Spatial\AxisOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(AxisOptions::class)]
#[Small]
final class AxisOptionsTest extends TestCase
{
    public function testParseNormalizesCaseAndLeavesTheDefaultUnspecified(): void
    {
        self::assertTrue(AxisOptions::parse(' AXIS-ORDER = LAT-LONG ', 'st_astext'));
        self::assertFalse(AxisOptions::parse('axis-order=long-lat', 'st_astext'));
        self::assertNull(AxisOptions::parse('axis-order=srid-defined', 'st_astext'));
        self::assertNull(AxisOptions::parse(' ', 'st_astext'));
    }

    public function testParseChecksDuplicateKeysBeforeSemanticValues(): void
    {
        $this->expectExceptionCode(3564);
        $this->expectExceptionMessage("Duplicate option key 'axis-order' in funtion 'st_astext'.");
        AxisOptions::parse('axis-order=bad,AXIS-ORDER=long-lat', 'st_astext');
    }

    public function testSeparatorsReportsASeparatedInvalidPair(): void
    {
        $this->expectExceptionCode(3563);
        $this->expectExceptionMessage("The options argument in function st_astext contains the invalid character sequence '=,'.");
        AxisOptions::separators('axis-order= ,x=y', 'st_astext');
    }
}
