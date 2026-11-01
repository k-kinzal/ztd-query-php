<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Function\Spatial\SpatialCast;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(SpatialCast::class)]
#[Small]
final class SpatialCastTest extends TestCase
{
    public function testEvaluateLooksUpACompleteSridHeaderBeforeReadingTheGeometryBody(): void
    {
        $this->expectExceptionCode(3548);
        $this->expectExceptionMessage("There's no spatial reference system with SRID 864190947.");
        (new Instance())->connect()->query("SELECT CAST('あ' '3' '2' AS POLYGON)");
    }

    public function testDomainAnswersTheResolvedGeometryType(): void
    {
        $domain = new Domain(Kind::String, Field::Geometry, 4294967295);
        self::assertSame($domain, (new SpatialCast(new Constant(Domain::null(), null), $domain, 'POINT'))->domain());
    }

    public function testEvaluateConvertsMembersAndPreservesNull(): void
    {
        $result = (new Instance())->connect()->query('SELECT CAST(Point(1,2) AS MULTIPOINT), CAST(NULL AS POINT)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[Wkb::write(new Geometry(4, [], [new Geometry(1, [[1.0, 2.0]])])), null]], $result->rows);
    }
}
