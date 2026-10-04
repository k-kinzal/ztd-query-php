<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonObjectAggregate::class)]
#[Small]
final class JsonObjectAggregateTest extends TestCase
{
    public function testAggregatesTellsWhetherTheCallHasNoWindow(): void
    {
        self::assertTrue((new JsonObjectAggregate(new StringLiteral(['x']), new NumberLiteral('1')))->aggregates());
    }

    public function testDeriveScalarAnswersJson(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new JsonObjectAggregate(new StringLiteral(['x']), new NumberLiteral('1')), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Json->descriptor()), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesEachAll(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new JsonObjectAggregate(new ColumnUse(new Name('a')), new ColumnUse(new Name('a')), true, false, new Name('w')))->render($out);

        self::assertSame('JSON_OBJECTAGG(ALL a, a) OVER w', (new Lexical())->join($out->pieces()));
    }
}
