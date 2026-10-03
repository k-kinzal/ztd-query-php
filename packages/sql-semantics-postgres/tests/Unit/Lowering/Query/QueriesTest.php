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
use SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;

#[CoversClass(Queries::class)]
#[Small]
final class QueriesTest extends TestCase
{
    public function testStatementLowersASelectStatement(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT a FROM t');
        self::assertInstanceOf(Select::class, $lowering->queries->statement($tree->find('SelectStmt')[0]));
    }

    public function testQueryLowersASelectStatementAsAQuery(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT a FROM t');
        $query = $lowering->queries->query($tree->find('select_no_parens')[0]);
        self::assertInstanceOf(Select::class, $query);
        self::assertCount(1, $query->targets);
    }

    public function testSortClauseIsEmptyWhenNoOrderByIsWritten(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT pg_catalog.int4(3) '1'");
        self::assertSame([], $lowering->queries->sortClause($tree->find('opt_sort_clause')[0]));
    }

    public function testSortClauseIsAnImplementationGapForAWrittenOrderBy(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1 DESC NULLS LAST');
        $this->expectExceptionMessage('No semantic rule is implemented for: sort_clause: ORDER BY sortby_list');
        $lowering->queries->sortClause($tree->find('sort_clause')[0]);
    }

    public function testSortDirectionIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1 DESC NULLS LAST');
        $this->expectExceptionMessage('No semantic rule is implemented for: opt_asc_desc: DESC');
        $lowering->queries->sortDirection($tree->find('opt_asc_desc')[0]);
    }

    public function testNullsOrderIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1 DESC NULLS LAST');
        $this->expectExceptionMessage('No semantic rule is implemented for: opt_nulls_order: NULLS_LA LAST_P');
        $lowering->queries->nullsOrder($tree->find('opt_nulls_order')[0]);
    }

    public function testWhereLowersThePredicateOrNothing(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT a FROM t WHERE a = 1');
        self::assertInstanceOf(BinaryOperation::class, $lowering->queries->where($tree->find('where_clause')[0]));
        self::assertNull($lowering->queries->where((new PostgreSqlParser('pg-17.2'))->parse('SELECT a FROM t')->find('where_clause')[0]));
    }

    public function testFromLowersTheInputsOfTheClause(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a, b AS x');
        $inputs = $lowering->queries->from($tree->find('from_clause')[0]);
        self::assertCount(2, $inputs);
        self::assertInstanceOf(TableInput::class, $inputs[1]);
        self::assertSame('x', $inputs[1]->alias?->value);
    }

    public function testRelationLowersAQualifiedName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM s.t');
        $relation = $lowering->queries->relation($tree->find('relation_expr')[0]);
        self::assertSame(['s', 't'], [$relation->name->schema?->value, $relation->name->name->value]);
    }

    public function testTableReferenceIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('MERGE INTO t USING s ON true WHEN MATCHED THEN DELETE');
        $this->expectExceptionMessage('No semantic rule is implemented for: table_ref: relation_expr opt_alias_clause');
        $lowering->queries->tableReference($tree->find('table_ref')[0]);
    }

    public function testRelationsIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('TRUNCATE a, b');
        $this->expectExceptionMessage('No semantic rule is implemented for: relation_expr_list: relation_expr_list , relation_expr');
        $lowering->queries->relations($tree->find('relation_expr_list')[0]);
    }

    public function testTargetsLowersTheSelectList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT a, b AS c FROM t');
        self::assertCount(2, $lowering->queries->targets($tree->find('target_list')[0]));
    }

    public function testWithIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('WITH w AS (SELECT 1) SELECT 1 FROM w');
        $this->expectExceptionMessage('No semantic rule is implemented for: with_clause: WITH cte_list');
        $lowering->queries->with($tree->find('with_clause')[0]);
    }
}
