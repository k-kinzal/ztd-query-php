<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Spatial;

use MySqlMemory\Value\Spatial\Wkt;
use MySqlMemory\Value\Spatial\WktInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(WktInput::class)]
#[Small]
final class WktInputTest extends TestCase
{
    public function testReadRetainsGeometryMembersAndTheirOrder(): void
    {
        $geometry = (new WktInput('GEOMETRYCOLLECTION(MULTIPOINT(1 2,3 4),POLYGON((0 0,1 0,1 1,0 0)))'))->read();
        self::assertNotNull($geometry);
        self::assertSame('GEOMETRYCOLLECTION(MULTIPOINT((1 2),(3 4)),POLYGON((0 0,1 0,1 1,0 0)))', Wkt::write($geometry));
        self::assertNull((new WktInput('POINT(1 2)tail'))->read());
        self::assertNotNull((new WktInput('POINT(1 2)tail', true))->read());
    }

    public function testGeometryBoundsRecursionAndRejectsUnknownNames(): void
    {
        self::assertNull((new WktInput('POINT(1 2)'))->geometry(64));
        self::assertNull((new WktInput('GEOMCOLLECTION()'))->read());
        self::assertSame([], (new WktInput('GEOMETRYCOLLECTION EMPTY'))->read()?->children);
    }

    public function testBodyRejectsMixedMultipointSyntaxAndUnclosedRings(): void
    {
        self::assertNull((new WktInput('(1 2,(3 4))'))->body(4, 0));
        self::assertNull((new WktInput('((0 0,1 0,1 1,2 2))'))->body(3, 0));
        self::assertNull((new WktInput('((1 2),(3 4))', true))->body(4, 0));
        self::assertNotNull((new WktInput('((1 2))', true))->body(3, 0));
    }

    public function testPointReadsTwoFiniteOrdinates(): void
    {
        self::assertSame([1.0, -2.0], (new WktInput('1-2'))->point());
        self::assertNull((new WktInput('1e309 2'))->point());
    }

    public function testNumberPreservesLegacyLeadingDotAndExponentRules(): void
    {
        self::assertNull((new WktInput('.1', true))->number());
        self::assertSame(-0.1, (new WktInput('-.1', true))->number());
        self::assertSame(2000.0, (new WktInput('2e3'))->number());
    }

    public function testTakeDoesNotAdvancePastAnUnexpectedPunctuation(): void
    {
        $reader = new WktInput(' ,1');
        self::assertFalse($reader->take(')'));
        self::assertTrue($reader->take(','));
        self::assertSame(1.0, $reader->number());
    }

    public function testSpaceConsumesWktWhitespaceWithoutChangingTheFollowingNumber(): void
    {
        $reader = new WktInput(" \t\n\r\v\f1");
        $reader->space();
        self::assertSame(1.0, $reader->number());
    }
    public function testOrdinatesRequiresOnePairForPointAndTwoForModernLine(): void
    {
        self::assertNull((new WktInput('1 2,3 4)'))->ordinates(1));
        self::assertNull((new WktInput('1 2)'))->ordinates(2));
        self::assertNotNull((new WktInput('1 2)', true))->ordinates(2));
    }

    public function testClosedChecksRingCountsBeforeInspectingEndpointValues(): void
    {
        $ring = new \MySqlMemory\Value\Spatial\Geometry(2, [[1.0, 2.0]]);
        self::assertFalse((new WktInput(''))->closed($ring));
        self::assertTrue((new WktInput('', true))->closed($ring));
        self::assertFalse((new WktInput('', true))->closed(new \MySqlMemory\Value\Spatial\Geometry(2)));
    }

}
