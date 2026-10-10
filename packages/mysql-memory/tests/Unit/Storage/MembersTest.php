<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\Globals;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Storage\Members;
use MySqlMemory\Storage\Store;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

#[CoversClass(Members::class)]
#[Small]
final class MembersTest extends TestCase
{
    public function testValueFindsAnEnumMemberInTheCollationOfTheColumn(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', new Domain(Kind::String, Field::Enum, 5, Domain::NOT_FIXED, false, $collation, true, ['small', 'large']), Fill::none());

        $value = (new Members(new Store($context)))->value('LARGE ', Domain::string(6, $collation), $column);

        self::assertSame(['large', []], [$value, $context->diagnostics->conditions]);
    }

    public function testValueStoresTheEmptyStringForAnUnknownEnumMember(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', new Domain(Kind::String, Field::Enum, 5, Domain::NOT_FIXED, false, $collation, true, ['small', 'large']), Fill::none());

        $value = (new Members(new Store($context, 2)))->value('huge', Domain::string(4, $collation), $column);

        self::assertSame(['', [['Warning', 1265, "Data truncated for column 'c' at row 2"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testValueRefusesAnUnknownEnumMemberUnderAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0, true);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', new Domain(Kind::String, Field::Enum, 5, Domain::NOT_FIXED, false, $collation, true, ['small', 'large']), Fill::none());

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Data truncated for column 'c' at row 1");

        (new Members(new Store($context)))->value('huge', Domain::string(4, $collation), $column);
    }

    public function testValueWritesSetMembersOnceInDeclaredOrder(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', new Domain(Kind::String, Field::Set, 5, Domain::NOT_FIXED, false, $collation, true, ['a', 'b', 'c']), Fill::none());

        $value = (new Members(new Store($context)))->value('c,A,c', Domain::string(5, $collation), $column);

        self::assertSame(['a,c', []], [$value, $context->diagnostics->conditions]);
    }

    public function testValueDropsUnknownSetMembersWithAWarning(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', new Domain(Kind::String, Field::Set, 5, Domain::NOT_FIXED, false, $collation, true, ['a', 'b', 'c']), Fill::none());

        $value = (new Members(new Store($context)))->value('b,x', Domain::string(3, $collation), $column);

        self::assertSame(['b', [['Warning', 1265, "Data truncated for column 'c' at row 1"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testValueStoresTheEmptySetForTheEmptyString(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', new Domain(Kind::String, Field::Set, 5, Domain::NOT_FIXED, false, $collation, true, ['a', 'b']), Fill::none());

        $value = (new Members(new Store($context)))->value('', Domain::string(0, $collation), $column);

        self::assertSame(['', []], [$value, $context->diagnostics->conditions]);
    }

    public function testMemberFindsAMemberByItsName(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', new Domain(Kind::String, Field::Enum, 2, Domain::NOT_FIXED, false, $collation, true, ['2', '1']), Fill::none());
        $members = new Members(new Store($context));

        self::assertSame(['1', '2', null], [$members->member('1 ', ['2', '1'], $column), $members->member('2', ['2', '1'], $column), $members->member('3', ['2', '1'], $column)]);
    }

    public function testValueReadsANumberAsTheEnumPosition(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('e', new Domain(Kind::String, Field::Enum, 1, Domain::NOT_FIXED, false, $collation, true, ['a', 'b']), Fill::none());
        $members = new Members(new Store($context));

        self::assertSame(['a', 'b', 'a', 'b', [], ''], [$members->value('1', Domain::integer(), $column), $members->value('2', Domain::integer(), $column), $members->value('1.9', Domain::decimal(2, 1), $column), $members->value('2.5', Domain::double(), $column), $context->diagnostics->conditions, $members->value('3', Domain::integer(), $column)]);
    }

    public function testValueRefusesTheNumberZeroForAnEnumButNotTheTextZero(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('e', new Domain(Kind::String, Field::Enum, 1, Domain::NOT_FIXED, false, $collation, true, ['a', 'b']), Fill::none());
        $members = new Members(new Store($context));

        self::assertSame(['', '', [['Warning', 1265, "Data truncated for column 'e' at row 1"]]], [$members->value('0', Domain::string(1, $collation), $column), $members->value('0', Domain::integer(), $column), $context->diagnostics->conditions]);
    }

    public function testValueReadsTheDigitsOfATextThatNamesNoEnumMemberAsThePosition(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('n', new Domain(Kind::String, Field::Enum, 1, Domain::NOT_FIXED, false, $collation, true, ['2', '1', 'x']), Fill::none());
        $members = new Members(new Store($context));

        self::assertSame(['1', '2', 'x', '2', '2', []], [$members->value('1', Domain::string(1, $collation), $column), $members->value('2', Domain::string(1, $collation), $column), $members->value('3', Domain::string(1, $collation), $column), $members->value(' +01 ', Domain::string(5, $collation), $column), $members->value('2 ', Domain::string(2, $collation), $column), $context->diagnostics->conditions]);
    }

    public function testValueReadsANumberAsTheBitsOfASet(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('s', new Domain(Kind::String, Field::Set, 5, Domain::NOT_FIXED, false, $collation, true, ['a', 'b', 'c']), Fill::none());
        $members = new Members(new Store($context));

        self::assertSame(['', 'a,b', 'a,b,c', 'b', 'a,b', []], [$members->value('0', Domain::integer(), $column), $members->value('3', Domain::integer(), $column), $members->value('7', Domain::integer(), $column), $members->value('2.9', Domain::decimal(2, 1), $column), $members->value(' 3', Domain::string(2, $collation), $column), $context->diagnostics->conditions]);
    }

    public function testValueKeepsTheSetBitsOfAnOutOfRangeNumberButNoneOfAnOutOfRangeText(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('s', new Domain(Kind::String, Field::Set, 5, Domain::NOT_FIXED, false, $collation, true, ['a', 'b', 'c']), Fill::none());
        $members = new Members(new Store($context));

        self::assertSame(['a', 'a,b,c', '', 'a', 4], [$members->value('9', Domain::integer(), $column), $members->value('-1', Domain::integer(), $column), $members->value('9', Domain::string(1, $collation), $column), $members->value('a,3', Domain::string(3, $collation), $column), count($context->diagnostics->conditions)]);
    }

    public function testIntegerKeepsTheIntegerPartOfANumber(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $members = new Members(new Store($context));

        self::assertSame(['2', '-1', '0', '0', '3', '18446744073709551615'], [$members->integer('2.9', Domain::decimal(2, 1)), $members->integer('-1.5', Domain::decimal(2, 1)), $members->integer('-0.4', Domain::decimal(2, 1)), $members->integer('-0.4', Domain::double()), $members->integer('3e0', Domain::double()), $members->integer('18446744073709551615', Domain::integer())]);
    }

    public function testDigitsReadsDigitsAfterSpacesAndAPlusSign(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $members = new Members(new Store($context));

        self::assertSame(['3', '0', null, '3', null, null], [$members->digits(' +03', false), $members->digits('00', false), $members->digits('3 ', false), $members->digits('3 ', true), $members->digits('-1', true), $members->digits('1e1', true)]);
    }

    public function testBitsAnswersTheMembersOfTheBitsOfANumber(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('m', new Domain(Kind::String, Field::Set, 5, Domain::NOT_FIXED, false, $collation, true, ['1', '4', 'x']), Fill::none());
        $members = new Members(new Store($context));

        self::assertSame(['4', '1,4', '1,4,x', [['Warning', 1265, "Data truncated for column 'm' at row 1"]]], [$members->bits('2', ['1', '4', 'x'], $column, true), $members->bits('3', ['1', '4', 'x'], $column, false), $members->bits('-1', ['1', '4', 'x'], $column, true), $context->diagnostics->conditions]);
    }
}
