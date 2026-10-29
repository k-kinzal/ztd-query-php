<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        yield '5.6' => ['mysql-5.6.51'];
        yield '5.7' => ['mysql-5.7.44'];
        yield '8.0' => ['mysql-8.0.44'];
        yield '8.4' => ['mysql-8.4.7'];
        yield '9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerReleases')]
    public function testDeriveScalarGivesTemporalExtremesTheirExpressionCollation(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $table = $semantics->analyze('CREATE TABLE t (d DATE)');
        $query = $semantics->analyze('SELECT MIN(d), MAX(d) FROM t', [$table]);
        $minimum = $query->field(0)->type;
        $maximum = $query->field(1)->type;
        self::assertInstanceOf(Known::class, $minimum);
        self::assertInstanceOf(Domain::class, $minimum->descriptor);
        self::assertInstanceOf(Known::class, $maximum);
        self::assertInstanceOf(Domain::class, $maximum->descriptor);

        self::assertSame(['latin1_swedish_ci', 'latin1_swedish_ci'], [$minimum->descriptor->collation->name, $maximum->descriptor->collation->name]);
        self::assertSame([Field::Date, Field::Date], [$minimum->descriptor->field, $maximum->descriptor->field]);
    }

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
        $order = new NumberLiteral('1.0');
        $fact = $derivation->scalar(new Aggregate(AggregateFunction::Average, [new NumberLiteral('1')], over: new WindowSpec(null, [], [new OrderItem($order)])), $derivation->environment());

        self::assertInstanceOf(Known::class, $fact->type);
        self::assertSame(TypeClass::Decimal, TypeClass::of($fact->type->descriptor));
        self::assertSame(Nullability::NotNull, $order->scalarInformation()->nullability);
    }

    public function testRenderWritesTheQuantifiersAndTheWindow(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new Aggregate(AggregateFunction::Count, [], false, true, new Name('w')))->render($out);

        self::assertSame('COUNT(ALL *) OVER w', (new Lexical())->join($out->pieces()));
    }

    public function testDeriveScalarResolvesTheLogicalTypesOfMinAndMaxOverAWindow(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a TINYINT, c CHAR(3))');
        $query = $semantics->analyze('SELECT MIN(a) OVER (), MAX(c) OVER (), MIN(a) FROM t', [$table]);

        self::assertEquals([new Known(Domain::integer(Field::Tiny, 4)), Field::String, Field::Tiny], [$query->field(0)->type, $query->field(1)->type instanceof Known && $query->field(1)->type->descriptor instanceof Domain ? $query->field(1)->type->descriptor->field : null, $query->field(2)->type instanceof Known && $query->field(2)->type->descriptor instanceof Domain ? $query->field(2)->type->descriptor->field : null]);
    }
    public function testInitializeDivisionPrecisionRetainsTheAverageCalculationIncrement(): void
    {
        $average = new Aggregate(AggregateFunction::Average, [new NumberLiteral('1')]);
        $average->initializeDivisionPrecision(12);
        self::assertSame(12, $average->divisionPrecision());
    }

    public function testDivisionPrecisionIsOwnedByThePublishedAverage(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('SELECT AVG(1)');
        $average = $query->field(0)->expression;
        self::assertInstanceOf(Aggregate::class, $average);
        self::assertSame(4, $average->divisionPrecision());
    }
}
