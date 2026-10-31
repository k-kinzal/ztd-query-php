<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastNotices;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

#[CoversClass(CastNotices::class)]
#[Medium]
final class CastNoticesTest extends TestCase
{
    public function testCapacityUsesTheResolvedCharacterSetBeforeRuntimeValues(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $target = new CastTarget(CastKind::Char, '2147483648');
        $domain = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::string(2147483648, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'));
        (new CastNotices())->capacity($target, $domain, $derivation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange::class, $derivation->facts()->diagnostics[0]);
    }

    public function testDeriveRetainsTheTargetNoticeWithoutRequiringAnOperand(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        (new CastNotices())->derive(new CastTarget(CastKind::NationalChar), $derivation);
        self::assertEquals([new Deprecation(Deprecated::National)], $derivation->facts()->warnings);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testCharsetRetainsTheUnknownNameAsATypedLookupCondition(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        (new CastNotices())->charset('missing_charset', $derivation);
        self::assertEquals([new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset('missing_charset')], $derivation->facts()->diagnostics);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $derivation->facts()->warnings[0]);
    }

    public function testCastRetainsTheTargetBoundBeforeResolvingAnUnknownOperand(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $problem = (new CastNotices())->cast(new CastTarget(CastKind::Time, '7'), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), $derivation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision::class, $problem);
        self::assertSame($problem, $derivation->facts()->diagnostics[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $derivation->facts()->warnings[0]);
        self::assertTrue($derivation->facts()->warnings[0]->aborts);
    }

    public function testReturningContinuesAfterASpatialTargetCondition(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $problem = (new CastNotices())->returning(new CastTarget(CastKind::Point), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), $derivation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast::class, $problem);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $derivation->facts()->warnings[0]);
        self::assertFalse($derivation->facts()->warnings[0]->aborts);
    }


    public function testLabelUsesOnlyTheOwningOperandSourceWhenPresent(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $operand = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('123', inputSource: new \SqlSemantics\Statement\Source\Span(12, 3));
        $bound = new \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize(\SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Precision, '', 66, 65);
        $problem = (new CastNotices())->label($bound, new CastTarget(CastKind::Decimal, '66'), $operand, $derivation);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput($bound, 12, 15), $problem);
    }

    public function testFailurePublishesTheSameConditionAsDiagnosticAndOrderedNotice(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $problem = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('AT LOCAL');
        self::assertSame($problem, (new CastNotices())->failure($problem, $derivation, false));
        self::assertSame([$problem], $derivation->facts()->diagnostics);
        self::assertEquals([new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem)], $derivation->facts()->warnings);
    }

    public function testAbortsDistinguishesContinuingLegacyCastConditions(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        self::assertFalse((new CastNotices())->aborts($derivation));
    }


    public function testBinaryStopsAfterItsUnavailableLocalCastOperand(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $operand = new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped(new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new CastTarget(CastKind::Signed), form: \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm::AtLocal));
        (new CastNotices())->binary($operand, $derivation);
        self::assertEquals([new Deprecation(Deprecated::BinaryOperator, aborts: true)], $derivation->facts()->warnings);
    }

}
