<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Function\Spatial\Relations;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Relations::class)]
#[Small]
final class RelationsTest extends TestCase
{
    public function testRoutinesRegistersTheTwoOperandKeyword(): void
    {
        $routines = (new Relations())->routines();
        self::assertCount(1, $routines);
        self::assertSame('CONTAINS', $routines[0]->name);
        self::assertTrue($routines[0]->accepts(2));
        self::assertFalse($routines[0]->accepts(1));
    }

    public function testContainsPreservesTheLegacyBoundaryRule(): void
    {
        $sql = 'SELECT CONTAINS(LINESTRING(POINT(0,0),POINT(2,2)),POINT(0,1)),CONTAINS(POINT(1,1),POINT(1,1))';
        $old = (new Instance('5.6.51'))->connect()->query($sql)[0];
        $new = (new Instance('5.7.44'))->connect()->query($sql)[0];
        self::assertInstanceOf(ResultSet::class, $old);
        self::assertInstanceOf(ResultSet::class, $new);
        self::assertSame([['1', '1']], $old->rows);
        self::assertSame([['0', '1']], $new->rows);
    }

    public function testGeometryPreservesTheVersionedMalformedInputCondition(): void
    {
        self::assertNull((new Relations())->geometry('invalid bytes', true));
        $this->expectExceptionMessage('Geometry byte string must be little endian.');
        (new Relations())->geometry('invalid bytes', false);
    }
}
