<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

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
