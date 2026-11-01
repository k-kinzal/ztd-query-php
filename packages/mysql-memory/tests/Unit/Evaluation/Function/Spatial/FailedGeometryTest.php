<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use LogicException;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Spatial\FailedGeometry;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(FailedGeometry::class)]
#[Small]
final class FailedGeometryTest extends TestCase
{
    public function testConditionKeepsTheOriginalConditionAndItsFailedGeometryResult(): void
    {
        $original = DataError::WrongValueForType->error('geohash', 'a', 'st_pointfromgeohash');
        $failure = FailedGeometry::condition($original);
        self::assertSame($original->getMessage(), $failure->getMessage());
        self::assertSame($original->getCode(), $failure->getCode());
        self::assertInstanceOf(FailedGeometry::class, $failure->getPrevious());
        self::assertSame($original, $failure->getPrevious()->getPrevious());
    }

    public function testReadRetainsTheReaderConditionAfterTheOriginalFailure(): void
    {
        $run = static function (): SqlError {
            try {
                (new Instance())->connect()->query("SELECT ST_ASTEXT(ST_POINTFROMGEOHASH('a',0))");
            } catch (SqlError $error) {
                return $error;
            }
            throw new LogicException('The malformed encoding must fail.');
        };
        $error = $run();
        self::assertSame(1411, $error->getCode());
        self::assertSame([[3037, 'Invalid GIS data provided to function st_astext.']], $error->following);
    }
}
