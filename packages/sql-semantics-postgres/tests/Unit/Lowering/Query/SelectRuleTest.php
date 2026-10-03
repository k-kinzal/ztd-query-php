<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;

#[CoversClass(SelectRule::class)]
#[Small]
final class SelectRuleTest extends TestCase
{
    public function testSelectLowersAPlainSelection(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT a, 1 FROM t AS x WHERE a < 1');
        $select = (new SelectRule($lowering))->select($tree->find('SelectStmt')[0]);
        self::assertCount(2, $select->targets);
        self::assertSame('x', $select->from?->alias?->value);
        self::assertInstanceOf(BinaryOperation::class, $select->where);
    }

    public function testSelectReportsAFormOutsideTheSlice(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT DISTINCT a FROM t');
        $this->expectExceptionMessage('No semantic rule is implemented for: simple_select: SELECT distinct_clause target_list');
        (new SelectRule($lowering))->select($tree->find('SelectStmt')[0]);
    }

    public function testSimpleReportsAClauseTheSliceRequiresToBeEmpty(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT a FROM t GROUP BY a');
        $this->expectExceptionMessage('No semantic rule is implemented for: group_clause: GROUP_P BY set_quantifier group_by_list');
        (new SelectRule($lowering))->simple($lowering->productions->form($tree->find('simple_select')[0]));
    }

    public function testSimpleReportsAFromClauseWithSeveralItems(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a, b');
        $this->expectExceptionMessage('a FROM clause with several items');
        (new SelectRule($lowering))->simple($lowering->productions->form($tree->find('simple_select')[0]));
    }

    public function testTargetsLowersLabelsWrittenWithAndWithoutAs(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT a AS "Select", b c, d FROM t');
        $targets = (new SelectRule($lowering))->targets($tree->find('target_list')[0]);
        self::assertCount(3, $targets);
        self::assertInstanceOf(ExpressionTarget::class, $targets[0]);
        self::assertInstanceOf(ExpressionTarget::class, $targets[1]);
        self::assertInstanceOf(ExpressionTarget::class, $targets[2]);
        self::assertSame(['Select', 'c', null], [$targets[0]->alias?->value, $targets[1]->alias?->value, $targets[2]->alias]);
    }

    public function testTargetsIsEmptyForAnEmptySelectList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT FROM t');
        self::assertSame([], (new SelectRule($lowering))->targets($tree->find('opt_target_list')[0]));
    }

    public function testTargetsReportsAStar(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT * FROM t');
        $this->expectExceptionMessage('No semantic rule is implemented for: target_el: *');
        (new SelectRule($lowering))->targets($tree->find('target_list')[0]);
    }

    public function testFromLowersOneNamedInput(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM s.t x');
        $inputs = (new SelectRule($lowering))->from($tree->find('from_clause')[0]);
        self::assertCount(1, $inputs);
        self::assertSame(['s', 't', 'x'], [$inputs[0]->name()->schema?->value, $inputs[0]->name()->name->value, $inputs[0]->alias?->value]);
        self::assertSame([], (new SelectRule($lowering))->from((new PostgreSqlParser('pg-17.2'))->parse('SELECT 1')->find('from_clause')[0]));
    }

    public function testFromReportsAJoin(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a JOIN b USING (c)');
        $this->expectExceptionMessage('No semantic rule is implemented for: table_ref: joined_table');
        (new SelectRule($lowering))->from($tree->find('from_clause')[0]);
    }

    public function testRelationReportsAnInheritanceMarker(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM ONLY t');
        $this->expectExceptionMessage('No semantic rule is implemented for: relation_expr: extended_relation_expr');
        (new SelectRule($lowering))->relation($tree->find('relation_expr')[0]);
    }

    public function testAliasLowersTheCorrelationNameOrNothing(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t AS "X"');
        self::assertSame('X', (new SelectRule($lowering))->alias($tree->find('opt_alias_clause')[0])?->value);
        self::assertNull((new SelectRule($lowering))->alias((new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t')->find('opt_alias_clause')[0]));
    }

    public function testAliasReportsAColumnList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t AS x (a)');
        $this->expectExceptionMessage('No semantic rule is implemented for: alias_clause: AS ColId ( name_list )');
        (new SelectRule($lowering))->alias($tree->find('opt_alias_clause')[0]);
    }

    public function testWhereLowersThePredicateOrNothing(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t WHERE a > 1');
        self::assertInstanceOf(BinaryOperation::class, (new SelectRule($lowering))->where($tree->find('where_clause')[0]));
        self::assertNull((new SelectRule($lowering))->where((new PostgreSqlParser('pg-17.2'))->parse('SELECT 1')->find('where_clause')[0]));
    }
}
