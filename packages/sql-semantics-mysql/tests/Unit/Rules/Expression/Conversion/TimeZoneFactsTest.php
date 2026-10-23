<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\TimeZoneFacts;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(TimeZoneFacts::class)]
#[Medium]
final class TimeZoneFactsTest extends TestCase
{
    public function testInputUsesFixedComparisonAndNumericCallFactsDespiteMissingOperands(): void
    {
        $rule = new TimeZoneFacts();
        self::assertFalse($rule->input(new Comparison(ComparisonOperator::Equal, new ColumnUse(new Name('absent')), new Parameter())));
        self::assertFalse($rule->input(new FunctionCall(new Name('ABS'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new ColumnUse(new Name('absent')))])));
        self::assertNull($rule->input(new FunctionCall(new Name('missing_routine'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new Parameter())])));
    }

    public function testInputLeavesCatalogAndParameterDependenciesOpen(): void
    {
        $rule = new TimeZoneFacts();
        self::assertNull($rule->input(new ColumnUse(new Name('a'))));
        self::assertNull($rule->input(new Parameter()));
        self::assertNull($rule->input(new NullLiteral()));
    }

    public function testExcludesRequiresEveryAlternativeToExcludeTheField(): void
    {
        $rule = new TimeZoneFacts();
        $integer = new Integral(IntegralKind::Int);
        $timestamp = new Temporal(TemporalKind::Timestamp);
        self::assertTrue($rule->excludes(new Known($integer)));
        self::assertTrue($rule->excludes(new Choice([$integer, new Temporal(TemporalKind::Date)])));
        self::assertFalse($rule->excludes(new Choice([$integer, $timestamp])));
        self::assertFalse($rule->excludes(new NullOnly()));
    }

    public function testDescriptorDistinguishesConcreteFieldsFromCoarseDatetimeFamilies(): void
    {
        $rule = new TimeZoneFacts();
        self::assertFalse($rule->descriptor(Domain::null()));
        self::assertFalse($rule->descriptor(new Domain(Kind::DateTime, Field::Timestamp, 19)));
        self::assertTrue($rule->descriptor(new Domain(Kind::DateTime, Field::DateTime, 19)));
        self::assertFalse($rule->descriptor(new Temporal(TemporalKind::DateTime)));
        self::assertTrue($rule->descriptor(Domain::integer(Field::LongLong, 21)));
    }
}
