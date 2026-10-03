<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Type\TypeNames;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TypeNames::class)]
#[Small]
final class TypeNamesTest extends TestCase
{
    public function testTypeNameLowersTheDesignationAndTheArrayBounds(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS int[][3])');
        $type = $lowering->types->typeName($tree->find('Typename')[0]);
        self::assertInstanceOf(KeywordDesignation::class, $type->designation);
        self::assertFalse($type->setOf);
        self::assertNotNull($type->array);
        self::assertCount(2, $type->array->bounds);
        self::assertSame('3', $type->array->bounds[1]->size?->digits);
    }

    public function testTypeNameLowersSetofAndTheArrayKeyword(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("CREATE FUNCTION f() RETURNS SETOF int ARRAY[2] AS 'x'");
        $type = $lowering->types->typeName($tree->find('Typename')[0]);
        self::assertTrue($type->setOf);
        self::assertNotNull($type->array);
        self::assertTrue($type->array->keyword);
        self::assertSame('2', $type->array->bounds[0]->size?->digits);
    }

    public function testSimpleLowersATypeWrittenAfterAsInASequenceOption(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE SEQUENCE s AS bigint');
        $designation = $lowering->types->simple($tree->find('SimpleTypename')[0]);
        self::assertInstanceOf(KeywordDesignation::class, $designation);
        self::assertSame(TypeKeyword::Bigint, $designation->keyword);
    }

    public function testIntervalLowersTheIntervalOfAZoneValue(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SET TIME ZONE INTERVAL '+01:00' HOUR TO MINUTE");
        $interval = $lowering->types->interval($tree->find('ConstInterval')[0], $tree->find('opt_interval')[0]);
        self::assertSame(IntervalFields::HourToMinute, $interval->fields);
    }

    public function testPreciseIntervalLowersTheSecondsPrecision(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SET TIME ZONE INTERVAL (3) '+01:00'");
        $interval = $lowering->types->preciseInterval($tree->find('ConstInterval')[0], $tree->find('Iconst')[0]);
        self::assertSame([null, '3'], [$interval->fields, $interval->precision?->digits]);
    }

    public function testBoundsIsNullWhenNoBracketIsWritten(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS int)');
        self::assertNull($lowering->types->bounds($tree->find('opt_array_bounds')[0]));
    }

    public function testFunctionTypeLowersAColumnTypeReference(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("CREATE FUNCTION f() RETURNS SETOF t.a%TYPE AS 'x'");
        $type = $lowering->types->functionType($tree->find('func_type')[0]);
        self::assertTrue($type->setOf);
        self::assertInstanceOf(ColumnDesignation::class, $type->designation);
        self::assertSame(['t', 'a'], array_map(static fn (Name $part): string => $part->value, $type->designation->name->parts));
    }

    public function testFunctionTypeLowersAPlainTypeName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("CREATE FUNCTION f() RETURNS text AS 'x'");
        self::assertInstanceOf(NamedDesignation::class, $lowering->types->functionType($tree->find('func_type')[0])->designation);
    }

    public function testTypeNamesLowersATypeList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('PREPARE p (int, text) AS SELECT 1');
        self::assertCount(2, $lowering->types->typeNames($tree->find('type_list')[0]));
    }

    public function testTypeNamesReportsAListThatIsNotATypeList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t AS x (a, b)');
        $this->expectExceptionMessage('No semantic rule is implemented for: name_list: name_list , name');
        $lowering->types->typeNames($tree->find('name_list')[0]);
    }

    public function testTypedColumnsLowersTheColumnsOfACompositeType(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TYPE t AS (a int, b text COLLATE "C")');
        $columns = $lowering->types->typedColumns($tree->find('OptTableFuncElementList')[0]);
        self::assertCount(2, $columns);
        self::assertSame('C', $columns[1]->collation?->last()->value);
        self::assertSame([], $lowering->types->typedColumns((new PostgreSqlParser('pg-17.2'))->parse('CREATE TYPE t AS ()')->find('OptTableFuncElementList')[0]));
    }

    public function testTypedColumnLowersOneColumn(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER TYPE t ADD ATTRIBUTE a int');
        $column = $lowering->types->typedColumn($tree->find('TableFuncElement')[0]);
        self::assertSame(['a', null], [$column->name->value, $column->collation]);
    }

    public function testConstantTypeLowersTheTypeOfATypedConstant(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT int4 '1', interval '1' day");
        $constants = $tree->find('AexprConst');
        self::assertInstanceOf(NamedDesignation::class, $lowering->types->constantType($lowering->productions->form($constants[0]))->designation);
        self::assertFalse($lowering->types->constantType($lowering->productions->form($constants[1]))->setOf);
    }

    public function testModifiersIsAnImplementationGapUntilTheInvocationFamilyLowersArguments(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT pg_catalog.int4(3, 4) '1'");
        $this->expectExceptionMessage('No semantic rule is implemented for: func_arg_list: func_arg_list , func_arg_expr');
        $lowering->types->modifiers($lowering->productions->form($tree->find('AexprConst')[0]));
    }
}
