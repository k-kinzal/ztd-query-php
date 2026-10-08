<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
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

        self::assertInstanceOf(Known::class, $fact->type);
        self::assertSame(TypeClass::Decimal, TypeClass::of($fact->type->descriptor));
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

    public function testDeriveScalarResolvesMinOverAWindowAsTheTemporaryTableHoldsIt(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a TINYINT, c CHAR(3))');
        $query = $semantics->analyze('SELECT MIN(a) OVER (), MAX(c) OVER (), MIN(a) FROM t', [$table]);

        self::assertEquals([new Known(Domain::integer(Field::Long, 4)), Field::VarString, Field::Tiny], [$query->field(0)->type, $query->field(1)->type instanceof Known && $query->field(1)->type->descriptor instanceof Domain ? $query->field(1)->type->descriptor->field : null, $query->field(2)->type instanceof Known && $query->field(2)->type->descriptor instanceof Domain ? $query->field(2)->type->descriptor->field : null]);
    }
}
