<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Names;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Names::class)]
#[Small]
final class NamesTest extends TestCase
{
    public function testNameDecodesAnIdentifierAndRecordsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 AS "Foo"');
        $name = $lowering->names->name($tree->find('ColLabel')[0]);
        self::assertSame('Foo', $name->value);
        self::assertSame([$name], $lowering->leaves->all());
    }

    public function testNameReadsAKeywordInEveryNamePosition(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 between');
        self::assertSame('between', $lowering->names->name($tree->find('BareColLabel')[0])->value);
    }

    public function testTokenDecodesAnIdentifierToken(): void
    {
        self::assertSame('foo', (new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172))->names->token(new Token(1, 'IDENT', 'FOO', 0))->value);
    }

    public function testTextReadsTheWordWithoutRecordingAnOperand(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 AS Bar');
        self::assertSame('bar', $lowering->names->text($tree->find('ColLabel')[0]));
        self::assertSame([], $lowering->leaves->all());
    }

    public function testNamesLowersANameList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t AS x (a, "B", c)');
        self::assertSame(['a', 'B', 'c'], array_map(static fn (Name $name): string => $name->value, $lowering->names->names($tree->find('name_list')[0])));
    }

    public function testNamesIsEmptyForAnAbsentColumnList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('COPY t FROM STDIN');
        self::assertSame([], $lowering->names->names($tree->find('opt_column_list')[0]));
    }

    public function testNamesLowersAColumnList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('COPY t (a, b) FROM STDIN');
        self::assertCount(2, $lowering->names->names($tree->find('opt_column_list')[0]));
    }

    public function testAttributesLowersTheNamesAfterTheDots(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP TABLE a.b.select');
        self::assertSame(['b', 'select'], array_map(static fn (Name $name): string => $name->value, $lowering->names->attributes($tree->find('attrs')[0])));
    }

    public function testFieldsLowersAnIndirectionOfFieldSelections(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a.b.c');
        self::assertSame(['b', 'c'], array_map(static fn (Name $name): string => $name->value, $lowering->names->fields($tree->find('indirection')[0])));
    }

    public function testDottedLowersAnyNameAndFuncName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT pg_catalog.int4 '1'");
        self::assertSame(['pg_catalog', 'int4'], array_map(static fn (Name $name): string => $name->value, $lowering->names->dotted($tree->find('func_name')[0])->parts));
    }

    public function testDottedListLowersEveryName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP TABLE a, b.c');
        $names = $lowering->names->dottedList($tree->find('any_name_list')[0]);
        self::assertSame([1, 2], [count($names[0]->parts), count($names[1]->parts)]);
    }

    public function testQualifiedLowersCatalogSchemaAndName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM c.s.t');
        $name = $lowering->names->qualified($tree->find('qualified_name')[0]);
        self::assertSame(['c', 's', 't'], [$name->catalog?->value, $name->schema?->value, $name->name->value]);
    }

    public function testQualifiedRejectsMoreThanThreeParts(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a.b.c.d');
        $this->expectException(AnalysisException::class);
        $lowering->names->qualified($tree->find('qualified_name')[0]);
    }

    public function testQualifiedListLowersEveryName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t () INHERITS (a, s.b)');
        self::assertCount(2, $lowering->names->qualifiedList($tree->find('qualified_name_list')[0]));
    }

    public function testOptionalLowersANameOrNothing(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX i ON t (a)');
        self::assertSame('i', $lowering->names->optional($tree->find('opt_single_name')[0])?->value);
        self::assertNull($lowering->names->optional((new PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t (a)')->find('opt_single_name')[0]));
    }

    public function testOptionalDottedLowersACollationOrNothing(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t (a COLLATE "C", b)');
        self::assertSame('C', $lowering->names->optionalDotted($tree->find('opt_collate')[0])?->last()->value);
        self::assertNull($lowering->names->optionalDotted($tree->find('opt_collate')[1]));
    }
}
