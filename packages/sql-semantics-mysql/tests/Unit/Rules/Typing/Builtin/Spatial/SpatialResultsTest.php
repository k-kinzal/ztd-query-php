<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Spatial\SpatialResults;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(SpatialResults::class)]
#[Small]
final class SpatialResultsTest extends TestCase
{
    public function testRulesResolveSridReadingAndReplacementSeparately(): void
    {
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $rules = (new SpatialResults())->rules();
        $read = $rules['ST_SRID'](new Invocation([Domain::null()], [], $settings, $derivation));
        $write = $rules['ST_SRID'](new Invocation([Domain::null(), Domain::integer()], [], $settings, $derivation));

        self::assertNotNull($read);
        self::assertNotNull($write);
        self::assertSame([Field::LongLong, 10, false, Field::Geometry], [$read->field, $read->length, $read->unsigned, $write->field]);
    }

    public function testRulesResolvePropertyWidthsAndTheConnectionCollation(): void
    {
        $call = new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $rules = (new SpatialResults())->rules();
        $type = $rules['ST_GEOMETRYTYPE']($call);

        self::assertSame(15, $type?->length);
        self::assertSame('utf8mb4_0900_ai_ci', $type->collation->name);
        self::assertSame(1, $rules['ST_ISEMPTY']($call)?->length);
        self::assertSame(10, $rules['ST_NUMGEOMETRIES']($call)?->length);
        self::assertSame(Field::Long, $rules['ST_DIMENSION']($call)?->storageField);
    }
    public function testRulesResolveGeometryAsBinaryWithCoercibleCollation(): void
    {
        $call = new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $rules = (new SpatialResults())->rules();
        $geometry = new Domain(Kind::String, Field::Geometry, 4294967295, 0, false, Collation::binary(), [], Coercibility::Coercible);
        self::assertEquals($geometry, $rules['POINT']($call));
        self::assertEquals($geometry, $rules['GEOMETRYCOLLECTION']($call));
    }
}
