<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\TimeZoneOperands;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone;
use SqlSemantics\Statement\Scalar;

#[CoversClass(DeferredTimeZone::class)]
#[CoversClass(TimeZoneOperands::class)]
#[CoversClass(AtTimeZone::class)]
#[Medium]
final class DeferredTimeZoneTest extends TestCase
{
    /**
     * @return iterable<string, array{Scalar}>
     */
    public static function providerIncompatible(): iterable
    {
        $values = [new NumberLiteral('1'), new BooleanLiteral(true), new StringLiteral(['2024-01-01 01:02:03']), new TemporalLiteral(TemporalForm::Date, '2024-01-01'), new TemporalLiteral(TemporalForm::Time, '01:02:03')];
        $values[] = new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::DateTime));
        $values[] = new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new NumberLiteral('1'));
        $values[] = new \SqlSemantics\Platform\MySql\Statement\Call\ClockCall(\SqlSemantics\Platform\MySql\Statement\Call\Clock::Now);
        foreach ($values as $index => $value) {
            yield 'literal:' . $index => [$value];
            yield 'grouped:' . $index => [new Grouped($value)];
        }
    }

    #[DataProvider('providerIncompatible')]
    public function testOperandsPreserveTheExpressionRequiringDeferredPreparation(Scalar $operand): void
    {
        $value = new DeferredTimeZone($operand, new Text('+00:00'));
        self::assertSame([$operand], $value->operands());
        $problem = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion::class, $problem);
        self::assertSame($value, $problem->expression);
        self::assertFalse((new TimeZoneOperands())->input($operand));
    }

    #[DataProvider('providerIncompatible')]
    public function testOrdinaryScalarConstructionRejectsIntrinsicTypeContradictions(Scalar $operand): void
    {
        $this->expectExceptionMessage('An AT TIME ZONE operand must admit a TIMESTAMP or NULL input role.');
        new AtTimeZone($operand, new Text('+00:00'));
    }

    public function testProblemRetainsTheOwningConversion(): void
    {
        $value = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        $problem = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion::class, $problem);
        self::assertSame($value, $problem->expression);
        self::assertTrue($problem->operand);
    }

    public function testProblemRetainsTheRowConstraintBeforeTheConversionConstraint(): void
    {
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new DeferredTimeZone($row, new Text('unknown'));
        self::assertSame([$row], $value->operands());
        self::assertSame('Operand should contain 1 column(s), not 2.', $value->problem()->message());
    }

    public function testDeriveScalarRetainsTheConstraintWithoutReportingItAtDefinition(): void
    {
        $value = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        $derivation = new \SqlSemantics\Construction\Derivation((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderKeepsTheCompleteStoredConversion(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $operation = $semantics->analyze("CREATE PROCEDURE p() SELECT CAST(1 AT TIME ZONE INTERVAL '+00:00' AS DATETIME(6))");
        self::assertSame($operation->toString(), $semantics->analyze($operation->toString())->toString());
    }
}
