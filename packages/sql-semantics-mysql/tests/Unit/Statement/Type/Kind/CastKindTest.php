<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

#[CoversClass(CastKind::class)]
#[Small]
final class CastKindTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEveryCastTarget(): void
    {
        self::assertSame(['Binary', 'Char', 'NationalChar', 'Signed', 'Unsigned', 'Date', 'Time', 'DateTime', 'Decimal', 'Json', 'Year', 'Real', 'Double', 'Float', 'Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon', 'GeometryCollection'], array_column(CastKind::cases(), 'name'));
        self::assertSame(['BINARY', 'CHAR', 'NCHAR', 'SIGNED', 'UNSIGNED', 'DATE', 'TIME', 'DATETIME', 'DECIMAL', 'JSON', 'YEAR', 'REAL', 'DOUBLE', 'FLOAT', 'POINT', 'LINESTRING', 'POLYGON', 'MULTIPOINT', 'MULTILINESTRING', 'MULTIPOLYGON', 'GEOMETRYCOLLECTION'], array_column(CastKind::cases(), 'value'));
    }
}
