<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Spatial\Constructors;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Constructors::class)]
#[Small]
final class ConstructorsTest extends TestCase
{
    public function testRoutinesRegisterTheSpatialConstructors(): void
    {
        self::assertCount(8, (new Constructors())->routines());
    }

    public function testPointConvertsCoordinatesAndPropagatesNull(): void
    {
        $result = (new Instance())->connect()->query("SELECT Point('1',2), Point(NULL,1)")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[Wkb::write(new Geometry(1, [[1.0, 2.0]])), null]], $result->rows);
    }

    public function testCollectionKeepsMemberOrder(): void
    {
        $result = (new Instance())->connect()->query('SELECT MultiPoint(Point(1,2),Point(3,4))')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[Wkb::write(new Geometry(4, [], [new Geometry(1, [[1.0, 2.0]]), new Geometry(1, [[3.0, 4.0]])]))]], $result->rows);
    }

    public function testValidateRefusesNonGeometricArgumentsBeforeEvaluation(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1367);
        (new Instance())->connect()->query('SELECT LineString(USER())');
    }
}
