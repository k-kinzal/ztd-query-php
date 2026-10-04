<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Functions;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\WrongArgumentCount;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Functions::class)]
#[Small]
final class FunctionsTest extends TestCase
{
    public function testRowFindsTheRowForTheArgumentCountCaseInsensitively(): void
    {
        $functions = new Functions();

        self::assertSame([1, 1, 'IR', 'P', 's'], $functions->row('ABS', 1));
        self::assertSame([1, 1, '1', 'Y', 'a'], $functions->row('max', 1));
        self::assertSame([2, -1, '+', 'P', 's'], $functions->row('Max', 2));
        self::assertSame([2, -1, '+', 'P', 's'], $functions->row('max', 5));
        self::assertSame([0, -1, 'T', 'N', 's'], $functions->row('char', 0));
        self::assertSame([0, 0, 'I', 'N', 'w'], $functions->row('row_number', 0));
    }

    public function testRowIsNullForAnUnknownNameOrArgumentCount(): void
    {
        $functions = new Functions();

        self::assertNull($functions->row('nosuch', 1));
        self::assertNull($functions->row('abs', 2));
        self::assertNull($functions->row('abs', 0));
        self::assertNull($functions->row('count', 2));
        self::assertNull($functions->row('row_number', 1));
    }

    public function testAggregatesHoldsForABuiltInAggregateCalledWithItsArguments(): void
    {
        $functions = new Functions();

        self::assertTrue($functions->aggregates('count', 0));
        self::assertTrue($functions->aggregates('COUNT', 1));
        self::assertTrue($functions->aggregates('sum', 1));
        self::assertTrue($functions->aggregates('max', 1));
        self::assertTrue($functions->aggregates('group_concat', 2));
        self::assertFalse($functions->aggregates('max', 2));
        self::assertFalse($functions->aggregates('row_number', 0));
        self::assertFalse($functions->aggregates('abs', 1));
        self::assertFalse($functions->aggregates('nosuch', 1));
    }

    public function testResultFollowsTheResultCodeOfTheRow(): void
    {
        $functions = new Functions();
        $integer = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $text = new ScalarFact(new Known(Storage::Text), Nullability::Nullable);
        $abs = $functions->result(new Name('abs'), [$integer]);
        $typeof = $functions->result(new Name('typeof'), [new ScalarFact(new NullOnly(), Nullability::Nullable)]);
        $likely = $functions->result(new Name('likely'), [$text]);
        $nullif = $functions->result(new Name('nullif'), [$integer, $text]);
        $substr = $functions->result(new Name('substr'), [$text, $integer]);
        $extract = $functions->result(new Name('json_extract'), [$text, $text]);

        self::assertInstanceOf(Choice::class, $abs->type);
        self::assertSame([Storage::Integer, Storage::Real], $abs->type->alternatives);
        self::assertSame(Nullability::NotNull, $abs->nullability);
        self::assertInstanceOf(Known::class, $typeof->type);
        self::assertSame(Storage::Text, $typeof->type->descriptor);
        self::assertSame(Nullability::NotNull, $typeof->nullability);
        self::assertInstanceOf(Known::class, $likely->type);
        self::assertSame(Storage::Text, $likely->type->descriptor);
        self::assertSame(Nullability::Nullable, $likely->nullability);
        self::assertInstanceOf(Known::class, $nullif->type);
        self::assertSame(Storage::Integer, $nullif->type->descriptor);
        self::assertSame(Nullability::Nullable, $nullif->nullability);
        self::assertInstanceOf(Choice::class, $substr->type);
        self::assertSame([Storage::Text, Storage::Blob], $substr->type->alternatives);
        self::assertInstanceOf(Choice::class, $extract->type);
        self::assertSame(Storage::cases(), $extract->type->alternatives);
    }

    public function testResultUnitesTheClassesOfTheArgumentsTheCodeSelects(): void
    {
        $functions = new Functions();
        $integer = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $real = new ScalarFact(new Known(Storage::Real), Nullability::NotNull);
        $text = new ScalarFact(new Known(Storage::Text), Nullability::NotNull);
        $null = new ScalarFact(new NullOnly(), Nullability::Nullable);
        $iif = $functions->result(new Name('iif'), [$integer, $text, $real]);
        $coalesce = $functions->result(new Name('coalesce'), [$null, new ScalarFact(new Known(Storage::Integer), Nullability::Nullable), $text]);
        $lag = $functions->result(new Name('lag'), [$integer, $real, $text]);
        $ifnull = $functions->result(new Name('ifnull'), [new ScalarFact(new Known(Storage::Integer), Nullability::Nullable), $null]);

        self::assertInstanceOf(Choice::class, $iif->type);
        self::assertSame([Storage::Real, Storage::Text], $iif->type->alternatives);
        self::assertSame(Nullability::Nullable, $iif->nullability);
        self::assertInstanceOf(Choice::class, $coalesce->type);
        self::assertSame([Storage::Integer, Storage::Text], $coalesce->type->alternatives);
        self::assertSame(Nullability::NotNull, $coalesce->nullability);
        self::assertInstanceOf(Choice::class, $lag->type);
        self::assertSame([Storage::Integer, Storage::Text], $lag->type->alternatives);
        self::assertInstanceOf(Known::class, $ifnull->type);
        self::assertSame(Storage::Integer, $ifnull->type->descriptor);
        self::assertSame(Nullability::Nullable, $ifnull->nullability);
    }

    public function testResultIsInvalidForAWrongArgumentCount(): void
    {
        $integer = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $result = (new Functions())->result(new Name('abs'), [$integer, $integer]);

        self::assertInstanceOf(Invalid::class, $result->type);
        self::assertInstanceOf(WrongArgumentCount::class, $result->type->cause);
        self::assertSame('abs', $result->type->cause->function->value);
        self::assertSame(2, $result->type->cause->arguments);
        self::assertSame('wrong number of arguments to function abs()', $result->type->cause->message());
        self::assertSame(Nullability::Dependent, $result->nullability);
    }

    public function testResultDependsOnAnUndeclaredRoutine(): void
    {
        $result = (new Functions())->result(new Name('nosuch'), [new ScalarFact(new Known(Storage::Integer), Nullability::NotNull)]);

        self::assertInstanceOf(Dependent::class, $result->type);
        self::assertCount(1, $result->type->missing);
        self::assertInstanceOf(UndeclaredRoutine::class, $result->type->missing[0]);
        self::assertSame('nosuch', $result->type->missing[0]->name->name->value);
        self::assertSame('the signature of routine nosuch', $result->type->missing[0]->describe());
        self::assertSame(Nullability::Dependent, $result->nullability);
    }

    public function testNullabilityOfTheFixedCodesIgnoresTheArguments(): void
    {
        $functions = new Functions();

        self::assertSame(Nullability::NotNull, $functions->nullability('N', [Nullability::Nullable]));
        self::assertSame(Nullability::NotNull, $functions->nullability('N', []));
        self::assertSame(Nullability::Nullable, $functions->nullability('Y', [Nullability::NotNull]));
        self::assertSame(Nullability::Nullable, $functions->nullability('P', []));
        self::assertSame(Nullability::Nullable, $functions->nullability('C', []));
    }

    public function testNullabilityPropagatesEveryArgumentForTheCodeP(): void
    {
        $functions = new Functions();

        self::assertSame(Nullability::NotNull, $functions->nullability('P', [Nullability::NotNull, Nullability::NotNull]));
        self::assertSame(Nullability::Nullable, $functions->nullability('P', [Nullability::NotNull, Nullability::Nullable]));
        self::assertSame(Nullability::Dependent, $functions->nullability('P', [Nullability::NotNull, Nullability::Dependent]));
        self::assertSame(Nullability::Nullable, $functions->nullability('P', [Nullability::Dependent, Nullability::Nullable]));
    }

    public function testNullabilityIsNotNullWhenAnyArgumentIsForTheCodeC(): void
    {
        $functions = new Functions();

        self::assertSame(Nullability::NotNull, $functions->nullability('C', [Nullability::Nullable, Nullability::NotNull]));
        self::assertSame(Nullability::NotNull, $functions->nullability('C', [Nullability::NotNull, Nullability::Dependent]));
        self::assertSame(Nullability::Nullable, $functions->nullability('C', [Nullability::Nullable, Nullability::Nullable]));
        self::assertSame(Nullability::Nullable, $functions->nullability('C', [Nullability::Dependent, Nullability::Nullable]));
        self::assertSame(Nullability::Dependent, $functions->nullability('C', [Nullability::Dependent, Nullability::Dependent]));
    }

    public function testNullabilityFollowsTheFirstArgumentForTheCodeF(): void
    {
        $functions = new Functions();

        self::assertSame(Nullability::Nullable, $functions->nullability('F', [Nullability::Nullable, Nullability::NotNull]));
        self::assertSame(Nullability::NotNull, $functions->nullability('F', [Nullability::NotNull, Nullability::Nullable]));
        self::assertSame(Nullability::Dependent, $functions->nullability('F', [Nullability::Dependent]));
    }
}
