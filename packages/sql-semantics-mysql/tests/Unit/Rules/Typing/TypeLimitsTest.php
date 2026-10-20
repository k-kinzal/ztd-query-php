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
        yield 'bit width' => ['BIT(65)', TypeLimit::Width];
        yield 'wide unsigned bit' => ['BIT(18446744073709551615)', TypeLimit::Width];
        yield 'wide decimal bit' => ['BIT(18446744073709551616)', TypeLimit::Width];
        yield 'floating width' => ['FLOAT(0,0)', TypeLimit::Width];
        yield 'floating precision' => ['FLOAT(54)', TypeLimit::Specifier];
        yield 'floating scale' => ['DOUBLE(50,31)', TypeLimit::Scale];
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
            yield $label . ' parameter' => ['CREATE PROCEDURE p(a ' . $type . ') SELECT 1', $rule, ''];
            yield $label . ' return' => ['CREATE FUNCTION f() RETURNS ' . $type . ' RETURN 1', $rule, ''];
            yield $label . ' alteration' => ['ALTER TABLE t ADD a ' . $type, $rule, 'a'];
            yield $label . ' local' => ['CREATE PROCEDURE p() BEGIN DECLARE a ' . $type . '; END', $rule, ''];
        }
    }

    #[DataProvider('providerDeclarations')]
    public function testCheckSharesBoundsAcrossDeclarations(string $sql, TypeLimit $rule, string $name): void
    {
        $semantics = new Semantics(Dialect::MySql);
        try {
            $semantics->analyze($sql);
            self::fail('An intrinsic size contradiction cannot publish a statement.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            $problem = $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody ? $failure->problem->problem : $failure->problem;
        }
        self::assertInstanceOf(InvalidTypeSize::class, $problem);
        self::assertSame([$rule, $name], [$problem->rule, $problem->name]);
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
    public function testCheckKeepsReleaseDependentBoundsInDerivation(string $sql, string $name): void
    {
        $problem = (new Semantics(Dialect::MySql))->analyze($sql)->facts->diagnostics[0];
        self::assertInstanceOf(InvalidTypeSize::class, $problem);
        self::assertSame([TypeLimit::Empty, $name], [$problem->rule, $problem->name]);
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

    public function testWidthChecksTheCommonBoundBeforeTheTypeSpecificBound(): void
    {
        $limits = new TypeLimits();
        $kind = \SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit;

        self::assertNull($limits->width(new \SqlSemantics\Platform\MySql\Statement\Type\Elementary($kind, '4294967295.9'), 'a'));
        self::assertEquals(new InvalidTypeSize(TypeLimit::Width, 'a', 4294967296, 4294967295), $limits->width(new \SqlSemantics\Platform\MySql\Statement\Type\Elementary($kind, '4294967296'), 'a'));
    }

    public function testFractionalFormatsLargeDecimalPrecisionAsASignedInteger(): void
    {
        $problem = (new TypeLimits())->fractional(new \SqlSemantics\Platform\MySql\Statement\Type\Decimal('4294967295'), 'a');

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

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testCheckAcceptsTheBoundaryWidthsAndFractions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(a BIT(1), b BIT(64), c INT(255), d FLOAT(53), e DOUBLE(255,30), f DECIMAL(65,30), g TIME(6), h BIT, i INT, j FLOAT, k DECIMAL(0,0))');

        self::assertSame([], $operation->facts->diagnostics);
    }
}
