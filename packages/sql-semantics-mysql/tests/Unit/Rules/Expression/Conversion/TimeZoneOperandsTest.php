<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\TimeZoneOperands;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TimeZoneOperands::class)]
#[Small]
final class TimeZoneOperandsTest extends TestCase
{
    public function testLiteralLeavesAColumnDependentWhileGroupingRetainsLiteralRoles(): void
    {
        $rule = new TimeZoneOperands();
        self::assertNull($rule->literal(new ColumnUse(new Name('t'))));
        self::assertFalse($rule->literal(new Grouped(new NumberLiteral('1'))));
        self::assertTrue($rule->literal(new Grouped(new TemporalLiteral(TemporalForm::Timestamp, '2024-01-01 01:02:03'))));
    }

    public function testInputKeepsNumericAggregateFamiliesOutsideTheTimestampRole(): void
    {
        $functions = \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::cases();
        $column = new ColumnUse(new Name('t'));
        $roles = array_map(static fn ($function): ?bool => (new TimeZoneOperands())->input(new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate($function, [$column])), $functions);
        $expected = array_map(static fn ($function): ?bool => in_array($function, [\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Minimum, \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Maximum], true) ? null : false, $functions);
        self::assertSame($expected, $roles);
    }

    public function testInputDistinguishesFixedResultsFromUnresolvedNames(): void
    {
        $rule = new TimeZoneOperands();
        $cast = new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new ColumnUse(new Name('t')), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::DateTime));
        self::assertFalse($rule->input(new Grouped($cast)));
        self::assertNull($rule->input(new Grouped(new ColumnUse(new Name('t')))));
    }

    public function testInputKeepsMinMaxFieldFamiliesDistinctFromLiteralSyntax(): void
    {
        $roles = array_map(static fn ($input): ?bool => (new TimeZoneOperands())->input(new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Minimum, [new Grouped($input)])), [new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new TemporalLiteral(TemporalForm::Timestamp, '2024-01-01 01:02:03'), new ColumnUse(new Name('t')), new \SqlSemantics\Platform\MySql\Statement\Call\Extract(\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day, new ColumnUse(new Name('t')))]);
        self::assertSame([false, true, false, null, false], $roles);
    }

    public function testFieldDoesNotPromoteATimestampLiteralToATimestampField(): void
    {
        $rule = new TimeZoneOperands();
        self::assertFalse($rule->field(new Grouped(new TemporalLiteral(TemporalForm::Timestamp, '2024-01-01 01:02:03'))));
        self::assertTrue($rule->field(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()));
        self::assertNull($rule->field(new ColumnUse(new Name('t'))));
    }
}
