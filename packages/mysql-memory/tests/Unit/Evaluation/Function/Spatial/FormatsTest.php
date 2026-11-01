<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Function\Spatial\Formats;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Formats::class)]
#[Small]
final class FormatsTest extends TestCase
{
    public function testRoutinesRegisterAllAliasesAndTheAxisExchange(): void
    {
        self::assertCount(9, (new Formats())->routines());
    }

    public function testValuePreservesSridWhileExportingTheRequestedAxisOrder(): void
    {
        $result = (new Instance())->connect()->query("SELECT ST_ASTEXT(ST_SRID(Point(1,2),4326)), ST_ASTEXT(ST_SRID(Point(1,2),4326),'axis-order=long-lat'), HEX(ST_ASBINARY(Point(1,2))), ST_ASTEXT(ST_SWAPXY(Point(1,2)))")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['POINT(2 1)', 'POINT(1 2)', '0101000000000000000000F03F0000000000000040', 'POINT(2 1)']], $result->rows);
    }

    public function testReferenceWarnsForUnknownIdentifiersBeforeReadingNullOptions(): void
    {
        $session = (new Instance())->connect();
        $bytes = bin2hex(Wkb::write(new Geometry(1, [[1.0, 2.0]], [], 1234567)));
        $session->query("SELECT ST_ASTEXT(X'" . $bytes . "',NULL)");
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '3565', "There's no spatial reference system with SRID 1234567. The axis order is unknown."]], $warnings->rows);
    }

    public function testLegacyKeepsBinaryTrailingDataButDoesNotRenderItAsText(): void
    {
        $bytes = Wkb::write(new Geometry(1, [[1.0, 2.0]])) . 'tail';
        self::assertSame(substr($bytes, 4), (new Formats())->legacy($bytes, 'binary'));
        self::assertSame('POINT(1 2)', (new Formats())->legacy($bytes, 'text'));
        self::assertNull((new Formats())->legacy('bad', 'text'));
    }
}
