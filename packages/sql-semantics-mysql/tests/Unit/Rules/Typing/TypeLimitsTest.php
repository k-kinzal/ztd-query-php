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
        yield 'bit width' => ['BIT(65)', TypeLimit::Width];
        yield 'wide unsigned bit' => ['BIT(18446744073709551615)', TypeLimit::Width];
        yield 'wide decimal bit' => ['BIT(18446744073709551616)', TypeLimit::Width];
        yield 'floating width' => ['FLOAT(0,0)', TypeLimit::Width];
        yield 'floating precision' => ['FLOAT(54)', TypeLimit::Specifier];
        yield 'floating scale' => ['DOUBLE(50,31)', TypeLimit::Scale];
        yield 'floating scale before width' => ['FLOAT(256,31)', TypeLimit::Scale];
        yield 'decimal precision' => ['DECIMAL(66,0)', TypeLimit::Precision];
        yield 'decimal scale order' => ['DECIMAL(2,3)', TypeLimit::ScaleExceedsPrecision];
        yield 'temporal fraction' => ['TIME(7)', TypeLimit::Precision];
        yield 'character length' => ['CHAR(256)', TypeLimit::Length];
        yield 'binary length' => ['BINARY(256)', TypeLimit::Length];
    }

    /**
     * @return iterable<string, array{string, TypeLimit, string}>
     */
    public static function providerDeclarations(): iterable
    {
        foreach (self::providerInvalidTypeSizes() as $label => [$type, $rule]) {
            yield $label . ' column' => ['CREATE TABLE t(a ' . $type . ')', $rule, 'a'];
            yield $label . ' alteration' => ['ALTER TABLE t ADD a ' . $type, $rule, 'a'];
        }
    }

    /**
     * @return iterable<string, array{string, TypeLimit}>
     */
    public static function providerStoredDeclarations(): iterable
    {
        foreach (self::providerInvalidTypeSizes() as $label => [$type, $rule]) {
            yield $label . ' parameter' => ['CREATE PROCEDURE p(a ' . $type . ') SELECT 1', $rule];
            yield $label . ' return' => ['CREATE FUNCTION f() RETURNS ' . $type . ' RETURN 1', $rule];
            yield $label . ' local' => ['CREATE PROCEDURE p() BEGIN DECLARE a ' . $type . '; END', $rule];
        }
    }

    #[DataProvider('providerStoredDeclarations')]
    public function testCheckIsNeverReachedForAStoredDeclarationTheReadingRefuses(string $sql, TypeLimit $rule): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('The type exceeds a bound.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(InvalidTypeSize::class, $failure->problem);
            self::assertSame($rule, $failure->problem->rule);
        }
    }

    #[DataProvider('providerDeclarations')]
    public function testCheckIsNeverReachedForABoundTheReadingRefuses(string $sql, TypeLimit $rule, string $name): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('The type exceeds a bound.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(InvalidTypeSize::class, $failure->problem);
            self::assertSame([$rule, $name], [$failure->problem->rule, $failure->problem->name]);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerLegacyBitDeclarations(): iterable
    {
        yield 'column' => ['CREATE TABLE t(a BIT(0))', 'a'];
        yield 'parameter' => ['CREATE PROCEDURE p(a BIT(0)) SELECT 1', ''];
        yield 'return' => ['CREATE FUNCTION f() RETURNS BIT(0) RETURN 1', ''];
        yield 'alteration' => ['ALTER TABLE t ADD a BIT(0)', 'a'];
        yield 'local' => ['CREATE PROCEDURE p() BEGIN DECLARE a BIT(0); END', ''];
    }

    #[DataProvider('providerLegacyBitDeclarations')]
    public function testCheckIsNeverReachedForAnEmptyBitTheReadingRefuses(string $sql, string $name): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('The bit width is empty.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(InvalidTypeSize::class, $failure->problem);
            self::assertSame([TypeLimit::Empty, $name], [$failure->problem->rule, $failure->problem->name]);
        }
    }

    public function testCheckIsNeverReachedForAParameterTheReadingRefuses(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f(a BIT(0)) RETURNS INT(11) RETURN 1');
            self::fail('The bit width is empty.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertSame("Invalid size for column ''.", $failure->problem->message());
        }
    }

    public function testSimpleAcceptsAnOmittedBitWidth(): void
    {
        $limits = new TypeLimits();

        self::assertNull($limits->simple(new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit), 'a'));
        self::assertNull($limits->simple(new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit, '64'), 'a'));
    }

    public function testFractionalAcceptsTheLargestDecimal(): void
    {
        self::assertNull((new TypeLimits())->fractional(new \SqlSemantics\Platform\MySql\Statement\Type\Decimal('65', '30'), 'a'));
    }

    public function testWidthAcceptsALengthWithinTheCommonBound(): void
    {
        $limits = new TypeLimits();
        $kind = \SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit;

        self::assertNull($limits->width(new \SqlSemantics\Platform\MySql\Statement\Type\Elementary($kind, '64.9'), 'a'));
        self::assertNull($limits->width(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int, '255'), 'a'));
    }

    public function testFractionalPartsFormatsLargeDecimalPrecisionAsASignedInteger(): void
    {
        $problem = (new TypeLimits())->fractionalParts(true, '4294967295', null, 'a');

        self::assertSame("Too-big precision -1 specified for 'a'. Maximum is 65.", $problem?->message());
    }

    public function testProblemKeepsTheReleaseSpecificEmptyBitDomain(): void
    {
        $limits = new TypeLimits();
        $type = new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit, '0');

        self::assertNull($limits->problem($type, '', \SqlSemantics\Contract\GrammarRelease::MySql5651));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Empty, ''), $limits->problem($type, '', \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testCheckAcceptsEmptyBitOnMySql56(): void
    {
        $operation = (new Semantics(Dialect::MySql, '5.6.51'))->analyze('CREATE FUNCTION f() RETURNS BIT(0) RETURN 1');

        self::assertSame([], $operation->diagnostics());
    }

    public function testCheckAcceptsTheBoundaryWidthsAndFractions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(a BIT(1), b BIT(64), c INT(255), d FLOAT(53), e DOUBLE(255,30), f DECIMAL(65,30), g TIME(6), h BIT, i INT, j FLOAT, k DECIMAL(0,0))');

        self::assertSame([], $operation->diagnostics());
    }

    public function testInvariantAcceptsADeclarationWithinEveryBound(): void
    {
        $type = new \SqlSemantics\Platform\MySql\Statement\Type\Decimal('65', '30');
        (new TypeLimits())->invariant($type);

        self::assertSame('65', $type->precision);
    }

    public function testOverflowReportsALengthAboveTheUnsigned32BitBound(): void
    {
        self::assertNull((new TypeLimits())->overflow('4294967295', 'a'));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Width, 'a', 4294967296, 4294967295), (new TypeLimits())->overflow('4294967296', 'a'));
    }

    public function testCharacterLengthBoundsCharAt255(): void
    {
        self::assertNull((new TypeLimits())->characterLength(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char, '255', 'a'));
        self::assertNull((new TypeLimits())->characterLength(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::VarChar, '256', 'a'));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Length, 'a', 256, 255), (new TypeLimits())->characterLength(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char, '256', 'a'));
    }

    public function testBinaryLengthBoundsBinaryAt255(): void
    {
        self::assertNull((new TypeLimits())->binaryLength(\SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind::VarBinary, '256', 'a'));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Length, 'a', 256, 255), (new TypeLimits())->binaryLength(\SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind::Binary, '256', 'a'));
    }

    public function testElementaryLengthBoundsBitBetweenOneAnd64(): void
    {
        self::assertNull((new TypeLimits())->elementaryLength(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit, '64', 'a'));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Empty, 'a'), (new TypeLimits())->elementaryLength(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit, '0', 'a'));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Width, 'a', 65, 64), (new TypeLimits())->elementaryLength(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit, '65', 'a'));
    }

    public function testIntegralWidthBoundsTheDisplayWidthAt255(): void
    {
        self::assertNull((new TypeLimits())->integralWidth(null, 'a'));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Width, 'a', 256, 255), (new TypeLimits())->integralWidth('256', 'a'));
    }

    public function testTemporalPrecisionBoundsTheFractionAt6(): void
    {
        self::assertNull((new TypeLimits())->temporalPrecision(\SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::Date, '9', 'a'));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Precision, 'a', 7, 6), (new TypeLimits())->temporalPrecision(\SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::DateTime, '7', 'a'));
    }

    public function testFractionalPartsOrdersScaleBeforePrecision(): void
    {
        self::assertEquals(new InvalidTypeSize(TypeLimit::Scale, 'a', 31, 30), (new TypeLimits())->fractionalParts(true, '66', '31', 'a'));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Precision, 'a', 66, 65), (new TypeLimits())->fractionalParts(true, '66', '30', 'a'));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Specifier, 'a'), (new TypeLimits())->fractionalParts(false, '54', null, 'a'));
        self::assertNull((new TypeLimits())->fractionalParts(false, '53', null, 'a'));
    }
}
