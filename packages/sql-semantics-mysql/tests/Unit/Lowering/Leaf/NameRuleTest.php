<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Leaf\NameRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;

#[CoversClass(NameRule::class)]
#[Medium]
final class NameRuleTest extends TestCase
{
    public function testIdentifierDecodesEverySpellingOfAName(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT a, `b c`, `d``e`, action, 猫 FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame('SELECT a, `b c`, `d``e`, `action`, `猫` FROM t', $operation->toString());
        $item2 = $operation->statement->items[2];
        self::assertInstanceOf(SelectExpression::class, $item2);
        self::assertInstanceOf(ColumnUse::class, $item2->expression);
        self::assertSame('d`e', $item2->expression->name->value);
    }

    public function testIdentifierReadsAStringAtANamePosition(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("SELECT a AS 'it''s' FROM t");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame("it's", $operation->statement->items[0]->alias?->value);
        self::assertSame("SELECT a AS `it's` FROM t", $operation->toString());
    }

    public function testIdentifierLowersALegacyKeywordAsTheNameItSpells(): void
    {
        self::assertSame('SELECT `ascii`, `action` FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT ascii, action FROM t')->toString());
    }

    public function testKeywordTellsTheKeywordAsIdentifierProductions(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new NameRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertTrue($rule->keyword('keyword: ASCII_SYM'));
        self::assertTrue($rule->keyword('keyword_sp: ACTION'));
        self::assertTrue($rule->keyword('ident_keywords_unambiguous: ACTION'));
        self::assertTrue($rule->keyword('ident_keywords_ambiguous_3_roles: EVENT_SYM'));
        self::assertFalse($rule->keyword('ident: IDENT_sys'));
        self::assertFalse($rule->keyword('IDENT_sys: IDENT'));
    }

    public function testOptionalLowersTheComponentOfALegacySystemVariable(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT @@innodb.x, @@y');

        self::assertInstanceOf(Select::class, $operation->statement);
        $item0 = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(SystemVariable::class, $item0->expression);
        self::assertSame('innodb', $item0->expression->instance?->value);
        self::assertSame('x', $item0->expression->name->value);
        $item1 = $operation->statement->items[1];
        self::assertInstanceOf(SelectExpression::class, $item1);
        self::assertInstanceOf(SystemVariable::class, $item1->expression);
        self::assertNull($item1->expression->instance);
    }

    public function testQualifiedLowersATableNameWithItsDatabase(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT a FROM `my db`.t');

        self::assertInstanceOf(Select::class, $operation->statement);
        $from = $operation->statement->from;
        self::assertInstanceOf(TableReference::class, $from);
        self::assertSame('my db', $from->name->schema?->value);
        self::assertSame('t', $from->name->name->value);
        self::assertSame('SELECT a FROM `my db`.t', $operation->toString());
    }

    public function testQualifiedListFlattensATableList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $rule = new NameRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $name = static fn (string $text): Node => new Node('table_name', 0, [new Node('table_ident', 0, [new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', $text, 0)])])])]);
        $list = new Node('table_list', 1, [new Node('table_list', 0, [$name('a')]), new Token(0, ',', ',', 0), $name('b')]);
        $names = $rule->qualifiedList($list);

        self::assertCount(2, $names);
        self::assertSame('a', $names[0]->name->value);
        self::assertSame('b', $names[1]->name->value);
        self::assertSame([], $rule->qualifiedList(new Node('opt_table_list', 0, [])));
        self::assertCount(1, $rule->qualifiedList(new Node('opt_table_list', 1, [new Node('table_list', 0, [$name('c')])])));
    }

    public function testIdentifiersFlattensAnIdentifierList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new NameRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $ident = static fn (string $text): Node => new Node('ident', 0, [new Node('IDENT_sys', 1, [new Token(0, 'IDENT_QUOTED', $text, 0)])]);
        $list = new Node('ident_string_list', 1, [new Node('ident_string_list', 0, [$ident('`p0`')]), new Token(0, ',', ',', 0), $ident('`p 1`')]);
        $names = $rule->identifiers($list);

        self::assertCount(2, $names);
        self::assertSame('p0', $names[0]->value);
        self::assertSame('p 1', $names[1]->value);
    }

    public function testColumnLowersEveryQualification(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT a, t.b, db.t.c FROM t');
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT .t.d FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        $item2 = $operation->statement->items[2];
        self::assertInstanceOf(SelectExpression::class, $item2);
        self::assertInstanceOf(ColumnUse::class, $item2->expression);
        self::assertSame('db', $item2->expression->qualifier?->schema?->value);
        self::assertSame('SELECT a, t.b, db.t.c FROM t', $operation->toString());
        self::assertInstanceOf(Select::class, $legacy->statement);
        $item0 = $legacy->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(ColumnUse::class, $item0->expression);
        self::assertNull($item0->expression->qualifier?->schema);
        self::assertSame('SELECT .t.d FROM t', $legacy->toString());
    }

    public function testColumnNameLowersADefinedColumnWithItsQualifiers(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $rule = new NameRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $ident = static fn (string $text): Node => new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', $text, 0)])]);
        $dot = new Token(0, '.', '.', 0);
        $bare = $rule->columnName(new Node('field_ident', 0, [$ident('a')]));
        $qualified = $rule->columnName(new Node('field_ident', 2, [$ident('t'), $dot, $ident('a')]));
        $full = $rule->columnName(new Node('field_ident', 1, [$ident('db'), $dot, $ident('t'), $dot, $ident('a')]));
        $leading = $rule->columnName(new Node('field_ident', 3, [$dot, $ident('a')]));

        self::assertSame('a', $bare->column->value);
        self::assertNull($bare->table);
        self::assertSame('t', $qualified->table?->name->value);
        self::assertSame('db', $full->table?->schema?->value);
        self::assertNull($leading->table);
    }

    public function testOptionalColumnNameLowersAnIndexName(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $rule = new NameRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $ident = new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', 'i', 0)])]);

        self::assertNull($rule->optionalColumnName(new Node('opt_ident', 0, [])));
        self::assertSame('i', $rule->optionalColumnName(new Node('opt_ident', 1, [new Node('field_ident', 0, [$ident])]))?->column->value);
    }

    public function testQualifierLowersTheTableAndDatabaseOfAColumnProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new NameRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $ident = static fn (string $text): Node => new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', $text, 0)])]);
        $dot = new Token(0, '.', '.', 0);
        $form = new Form(new Node('simple_ident_q', 1, [$ident('db'), $dot, $ident('t'), $dot, $ident('a')]), 'simple_ident_q: ident . ident . ident');

        self::assertNull($rule->qualifier($form, null, null));
        self::assertSame('t', $rule->qualifier($form, 2, null)?->name->value);
        self::assertSame('db', $rule->qualifier($form, 2, 0)?->schema?->value);
    }

    public function testWildcardLowersAQualifiedStar(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new NameRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $ident = static fn (string $text): Node => new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', $text, 0)])]);
        $dot = new Token(0, '.', '.', 0);
        $star = new Token(0, '*', '*', 0);
        $table = $rule->wildcard(new Node('table_wild', 0, [$ident('t'), $dot, $star]));
        $qualified = $rule->wildcard(new Node('table_wild', 1, [$ident('db'), $dot, $ident('t'), $dot, $star]));

        self::assertSame('t', $table->table->name->value);
        self::assertNull($table->table->schema);
        self::assertSame('db', $qualified->table->schema?->value);
    }

    public function testClaimedReportsAnUnexpectedListProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new NameRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: table_list: *');

        $rule->claimed(new Form(new Node('table_list', 0, []), 'table_list: *'), ['table_list: table_ident']);
    }

    public function testDottedTellsATableNameWrittenAfterALeadingDot(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 FROM .t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableReference::class, $operation->statement->from);
        self::assertSame(OptionalWords::Written, $operation->statement->from->dot);
        self::assertSame('SELECT 1 FROM .t', $operation->toString());
    }
}
