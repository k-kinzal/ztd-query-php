<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\TypeLimits;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit;

#[CoversClass(TypeLimits::class)]
#[CoversClass(InvalidTypeSize::class)]
#[Medium]
final class TypeLimitsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, TypeLimit}>
     */
    public static function providerInvalidTypeSizes(): iterable
    {
        yield 'integer width' => ['INT(256)', TypeLimit::Width];
        yield 'empty bit' => ['BIT(0)', TypeLimit::Empty];
        yield 'bit width' => ['BIT(65)', TypeLimit::Width];
        yield 'floating width' => ['FLOAT(0,0)', TypeLimit::Width];
        yield 'floating precision' => ['FLOAT(54)', TypeLimit::Specifier];
        yield 'floating scale' => ['DOUBLE(50,31)', TypeLimit::Scale];
        yield 'decimal precision' => ['DECIMAL(66,0)', TypeLimit::Precision];
        yield 'decimal scale order' => ['DECIMAL(2,3)', TypeLimit::ScaleExceedsPrecision];
        yield 'temporal fraction' => ['TIME(7)', TypeLimit::Precision];
    }

    #[DataProvider('providerInvalidTypeSizes')]
    public function testCheckSharesBoundsBetweenColumnsParametersAndReturns(string $type, TypeLimit $rule): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $column = $semantics->analyze('CREATE TABLE t(a ' . $type . ')')->facts->diagnostics[0];
        $parameter = $semantics->analyze('CREATE PROCEDURE p(a ' . $type . ') SELECT 1')->facts->diagnostics[0];
        $result = $semantics->analyze('CREATE FUNCTION f() RETURNS ' . $type . ' RETURN 1')->facts->diagnostics[0];

        self::assertInstanceOf(InvalidTypeSize::class, $column);
        self::assertInstanceOf(InvalidTypeSize::class, $parameter);
        self::assertInstanceOf(InvalidTypeSize::class, $result);
        self::assertSame([$rule, 'a'], [$column->rule, $column->name]);
        self::assertSame([$rule, ''], [$parameter->rule, $parameter->name]);
        self::assertSame([$rule, ''], [$result->rule, $result->name]);
    }

    public function testCheckPlacesTheAbortingFailureBeforeLaterWarnings(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f(a BIT(0)) RETURNS INT(11) RETURN 1');

        self::assertInstanceOf(ParseFailure::class, $operation->facts->warnings[0]);
        self::assertTrue($operation->facts->warnings[0]->aborts);
        self::assertSame("Invalid size for column ''.", $operation->facts->warnings[0]->message());
    }

    public function testSimpleReportsBitOverflowAndAcceptsAnOmittedWidth(): void
    {
        $limits = new TypeLimits();

        self::assertEquals(new InvalidTypeSize(TypeLimit::Width, 'a', 65, 64), $limits->simple(new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit, '65'), 'a'));
        self::assertNull($limits->simple(new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit), 'a'));
    }

    public function testFractionalReportsScaleBeforePrecision(): void
    {
        $problem = (new TypeLimits())->fractional(new \SqlSemantics\Platform\MySql\Statement\Type\Decimal('66', '31'), 'a');

        self::assertEquals(new InvalidTypeSize(TypeLimit::Scale, 'a', 31, 30), $problem);
    }

    public function testCheckAcceptsEmptyBitOnMySql56(): void
    {
        $operation = (new Semantics(Dialect::MySql, '5.6.51'))->analyze('CREATE FUNCTION f() RETURNS BIT(0) RETURN 1');

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testCheckAcceptsTheBoundaryWidthsAndFractions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(a BIT(1), b BIT(64), c INT(255), d FLOAT(53), e DOUBLE(255,30), f DECIMAL(65,30), g TIME(6), h BIT, i INT, j FLOAT, k DECIMAL(0,0))');

        self::assertSame([], $operation->facts->diagnostics);
    }
}
