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
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Aggregate::class)]
#[Small]
final class AggregateTest extends TestCase
{
    public function testStarTellsCountOfAllRows(): void
    {
        self::assertTrue((new Aggregate(AggregateFunction::Count, []))->star());
        self::assertFalse((new Aggregate(AggregateFunction::Count, [new NumberLiteral('1')]))->star());
    }

    public function testAggregatesTellsWhetherTheCallHasNoWindow(): void
    {
        self::assertTrue((new Aggregate(AggregateFunction::Sum, [new NumberLiteral('1')]))->aggregates());
        self::assertFalse((new Aggregate(AggregateFunction::Sum, [new NumberLiteral('1')], over: new WindowSpec()))->aggregates());
    }

    public function testDeriveScalarDerivesTheWindow(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $order = new NumberLiteral('1');
        $fact = $derivation->scalar(new Aggregate(AggregateFunction::Average, [new NumberLiteral('1')], over: new WindowSpec(null, [], [new OrderItem($order)])), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Decimal->descriptor()), $fact->type);
        self::assertSame(Nullability::NotNull, $derivation->facts()->scalar($order)->nullability);
    }

    public function testRenderWritesTheQuantifiersAndTheWindow(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new Aggregate(AggregateFunction::Count, [], false, true, new Name('w')))->render($out);

        self::assertSame('COUNT(ALL *) OVER w', (new Lexical())->join($out->pieces()));
    }

}
