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
        foreach ($values as $index => $value) {
            yield 'literal:' . $index => [$value];
            yield 'grouped:' . $index => [new Grouped($value)];
        }
    }

    #[DataProvider('providerIncompatible')]
    public function testOperandsPreserveTheLiteralRequiringDeferredPreparation(Scalar $operand): void
    {
        $value = new DeferredTimeZone($operand, new Text('+00:00'));
        self::assertSame([$operand], $value->operands());
        self::assertSame($value, $value->problem()->expression);
        self::assertFalse((new TimeZoneOperands())->literal($operand));
    }

    public function testProblemRetainsTheOwningConversion(): void
    {
        $value = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        self::assertSame($value, $value->problem()->expression);
        self::assertTrue($value->problem()->operand);
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
