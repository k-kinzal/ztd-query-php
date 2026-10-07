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

    public function testMemberReadsANumberAsAPosition(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', new Domain(Kind::String, Field::Enum, 5, Domain::NOT_FIXED, false, $collation, true, ['small', 'large']), Fill::none());
        $members = new Members(new Store($context));

        self::assertSame(['small', 'large', null], [$members->member('1', Domain::integer(), ['small', 'large'], $column), $members->member('2', Domain::integer(), ['small', 'large'], $column), $members->member('3', Domain::integer(), ['small', 'large'], $column)]);
    }

    public function testMemberReadsTheDigitsOfAStringAsAName(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', new Domain(Kind::String, Field::Enum, 2, Domain::NOT_FIXED, false, $collation, true, ['1', '2']), Fill::none());
        $members = new Members(new Store($context));

        self::assertSame(['2', null], [$members->member('2', Domain::string(1, $collation), ['1', '2'], $column), $members->member('3', Domain::string(1, $collation), ['1', '2'], $column)]);
    }
}
